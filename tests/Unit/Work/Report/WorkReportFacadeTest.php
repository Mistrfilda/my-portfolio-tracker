<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Admin\AppAdmin;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Work\Report\WorkReport;
use App\Work\Report\WorkReportAiSummaryGenerator;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportFacade;
use App\Work\Report\WorkReportPeriod;
use App\Work\Report\WorkReportRepository;
use App\Work\Report\WorkReportSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** @phpstan-import-type WorkReportData from WorkReportBuilder */
class WorkReportFacadeTest extends TestCase
{

	public function testSourceSnapshotIsFlushedBeforeAiAndSurvivesAFailedSummary(): void
	{
		$data = $this->data();
		$builder = $this->createStub(WorkReportBuilder::class);
		$builder->method('build')->willReturn($data);
		$repository = $this->createStub(WorkReportRepository::class);
		$repository->method('getByReportDateForAppAdmin')->willReturn(null);
		$report = null;
		$snapshots = [];
		$em = $this->createMock(EntityManagerInterface::class);
		$em->expects(self::once())->method('persist')->willReturnCallback(
			static function (object $entity) use (&$report): void {
				self::assertInstanceOf(WorkReport::class, $entity);
				$report = $entity;
			},
		);
		$em->expects(self::exactly(2))->method('flush')->willReturnCallback(
			static function () use (&$report, &$snapshots): void {
				self::assertInstanceOf(WorkReport::class, $report);
				$snapshots[] = $report->getData();
			},
		);
		$summary = $this->createMock(WorkReportAiSummaryGenerator::class);
		$summary->expects(self::once())->method('generate')->willReturnCallback(
			static function (array $input) use (&$snapshots, $data): array {
				self::assertSame([$data], $snapshots);
				self::assertSame($data, $input);
				return ['status' => 'failed', 'model' => 'gpt-6-luna', 'generatedAt' => '2026-10-27T07:00:00+01:00',
					'bullets' => [], 'error' => 'AI summary generation failed.'];
			},
		);
		$saved = $this->facade($builder, $repository, $em, $summary)->generate(WorkReportPeriod::create('2026-10-27'));
		self::assertSame('complete', $saved->getData()['status']);
		self::assertSame([], $saved->getData()['warnings']);
		self::assertSame('failed', $saved->getData()['aiSummary']['status']);
		self::assertCount(2, $snapshots);
	}

	public function testSummaryOnlyUsesExistingSnapshotWithoutBuildingOrCreatingAnotherRow(): void
	{
		$data = $this->data();
		$report = new WorkReport(
			$this->createStub(AppAdmin::class),
			new ImmutableDateTime('2026-10-27'),
			$data,
			new ImmutableDateTime(),
		);
		$builder = $this->createMock(WorkReportBuilder::class);
		$builder->expects(self::never())->method('build');
		$repository = $this->createStub(WorkReportRepository::class);
		$repository->method('getByReportDateForAppAdmin')->willReturn($report);
		$em = $this->createMock(EntityManagerInterface::class);
		$em->expects(self::never())->method('persist');
		$em->expects(self::once())->method('flush');
		$summary = $this->createStub(WorkReportAiSummaryGenerator::class);
		$summary->method('generate')->willReturn([
			'status' => 'skipped', 'model' => 'gpt-6-luna', 'generatedAt' => '2026-10-27T07:00:00+01:00', 'bullets' => [], 'error' => null,
		]);
		$saved = $this->facade($builder, $repository, $em, $summary)->summarize(new ImmutableDateTime('2026-10-27'));
		self::assertSame($report, $saved);
		$after = $saved->getData();
		unset($after['aiSummary']);
		self::assertSame($data, $after);
	}

	private function facade(
		WorkReportBuilder $builder,
		WorkReportRepository $repository,
		EntityManagerInterface $em,
		WorkReportAiSummaryGenerator $summary,
	): WorkReportFacade
	{
		$owner = $this->createStub(WorkMonthlyIncomeOwnerProvider::class);
		$owner->method('getOwner')->willReturn($this->createStub(AppAdmin::class));
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-10-27T07:00:00+01:00'));
		return new WorkReportFacade(
			$owner,
			$builder,
			$repository,
			$em,
			$clock,
			new NullLogger(),
			$summary,
			new WorkReportSanitizer('synthetic', null),
		);
	}

	/** @return WorkReportData */
	private function data(): array
	{
		return [
			'schemaVersion' => 1, 'reportDate' => '2026-10-27', 'generatedAt' => '2026-10-27T07:00:00+01:00', 'status' => 'complete',
			'period' => ['from' => '2026-10-26', 'to' => '2026-10-26', 'timezone' => 'Europe/Prague'],
			'totals' => ['hours' => 0.0, 'roundedHours' => 0.0, 'entryCount' => 0, 'projectCount' => 0],
			'projects' => [], 'gitlabItems' => [], 'warnings' => [],
		];
	}

}
