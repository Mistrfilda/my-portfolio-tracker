<?php

declare(strict_types = 1);

namespace App\Test\Integration\Work;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Test\Integration\IntegrationTestCase;
use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\GitLab\GitLabReportClient;
use App\Work\Harvest\HarvestReportClient;
use App\Work\Report\Command\GenerateWorkReportCommand;
use App\Work\Report\WorkReport;
use App\Work\Report\WorkReportAiSummaryGenerator;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportFacade;
use App\Work\Report\WorkReportPeriod;
use App\Work\Report\WorkReportRepository;
use App\Work\Report\WorkReportSanitizer;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;

class WorkReportTest extends IntegrationTestCase
{

	public function testConsoleCommandCanBeResolvedFromContainer(): void
	{
		$command = $this->getService(GenerateWorkReportCommand::class);
		self::assertSame('work:report:generate', $command->getName());
		self::assertTrue($command->getDefinition()->hasOption('date'));
		self::assertTrue($command->getDefinition()->hasOption('output'));
	}

	public function testRegenerationUpdatesOneJsonSnapshotAndIsIsolatedByOwner(): void
	{
		$entityManager = $this->getService(EntityManagerInterface::class);
		$owners = [$this->createOwner(), $this->createOwner()];
		$period = WorkReportPeriod::create('2026-10-27');
		$repository = $this->getService(WorkReportRepository::class);
		$this->getService(CurrentAppAdminGetter::class)->setApiAppAdmin($owners[1]);
		$first = $this->facade($owners[0], $this->emptyBuilder())->generate($period);
		$originalId = $first->getId()->toString();
		$second = $this->facade($owners[1], $this->emptyBuilder())->generate($period);
		$replacement = $first->getData();
		$replacement['status'] = 'partial';
		$replacement['warnings'] = ['A GitLab item is unavailable after regeneration.'];
		$builder = $this->createStub(WorkReportBuilder::class);
		$builder->method('build')->willReturn($replacement);
		$this->facade($owners[0], $builder)->generate($period);
		self::assertSame(1, (int) $this->getService(Connection::class)->fetchOne(
			'SELECT COUNT(*) FROM work_report WHERE app_admin_id = ?',
			[$owners[0]->getId()->toString()],
		));
		self::assertNotSame($first->getId()->toString(), $second->getId()->toString());
		$entityManager->clear();
		$loaded = $repository->getByReportDateForAppAdmin($period->reportDate, $owners[0]);
		self::assertInstanceOf(WorkReport::class, $loaded);
		self::assertSame($originalId, $loaded->getId()->toString());
		self::assertSame('2026-10-27', $loaded->getReportDate()->format('Y-m-d'));
		self::assertSame(
			['from' => '2026-10-26', 'to' => '2026-10-26', 'timezone' => 'Europe/Prague'],
			$loaded->getData()['period'],
		);
		self::assertSame([], $loaded->getData()['projects']);
		self::assertSame($replacement['warnings'], $loaded->getData()['warnings']);
		self::assertSame('partial', $loaded->getData()['status']);
		$otherOwnerReport = $repository->getByReportDateForAppAdmin($period->reportDate, $owners[1]);
		self::assertNotNull($otherOwnerReport);
		self::assertSame([], $otherOwnerReport->getData()['warnings']);
		self::assertNull($repository->findForCurrentAppAdmin($loaded->getId()));
		self::assertSame($otherOwnerReport, $repository->findForCurrentAppAdmin($otherOwnerReport->getId()));
		self::assertNull($repository->findForCurrentAppAdmin(Uuid::uuid4()));
		$this->getService(CurrentAppAdminGetter::class)->endApiRequest();
	}

	public function testDownloadFailurePreservesExistingSnapshot(): void
	{
		$owner = $this->createOwner();
		$period = WorkReportPeriod::create('2026-10-27');
		$original = $this->facade($owner, $this->emptyBuilder())->generate($period);
		$originalData = $original->getData();
		$builder = $this->createStub(WorkReportBuilder::class);
		$builder->method('build')->willThrowException(new WorkReportException('Harvest returned HTTP 500.'));
		try {
			$this->facade($owner, $builder)->generate($period);
			self::fail('Expected a failed download.');
		} catch (WorkReportException) {
			self::assertSame($originalData, $original->getData());
			$this->getService(EntityManagerInterface::class)->clear();
			$stored = $this->getService(WorkReportRepository::class)->getByReportDateForAppAdmin(
				$period->reportDate,
				$owner,
			);
			self::assertSame($originalData, $stored?->getData());
		}
	}

	private function createOwner(): AppAdmin
	{
		$suffix = Uuid::uuid4()->toString();
		$owner = new AppAdmin(
			'Report owner',
			'work-report-' . $suffix,
			$suffix . '@example.com',
			'synthetic',
			$this->createDatetime(),
			false,
			false,
		);
		$entityManager = $this->getService(EntityManagerInterface::class);
		$entityManager->persist($owner);
		$entityManager->flush();
		return $owner;
	}

	private function emptyBuilder(): WorkReportBuilder
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([]);
		return new WorkReportBuilder(
			$harvest,
			$this->createStub(GitLabReportClient::class),
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
			new NullLogger(),
		);
	}

	private function facade(AppAdmin $owner, WorkReportBuilder $builder): WorkReportFacade
	{
		$summary = $this->createStub(WorkReportAiSummaryGenerator::class);
		$summary->method('generate')->willReturn([
			'status' => 'skipped', 'model' => 'gpt-6-luna', 'generatedAt' => '2026-10-27T07:00:00+01:00',
			'bullets' => [], 'error' => null,
		]);
		return new WorkReportFacade(
			new WorkMonthlyIncomeOwnerProvider($this->getService(AppAdminRepository::class), $owner->getUsername()),
			$builder,
			$this->getService(WorkReportRepository::class),
			$this->getService(EntityManagerInterface::class),
			$this->getDatetimeFactory(),
			new NullLogger(),
			$summary,
			new WorkReportSanitizer('synthetic', null),
		);
	}

}
