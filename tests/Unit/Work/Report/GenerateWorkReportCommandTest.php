<?php

declare(strict_types = 1);

namespace App\Test\Unit\Work\Report;

use App\Admin\AppAdmin;
use App\Work\GitLab\GitLabReferenceExtractor;
use App\Work\GitLab\GitLabReportClient;
use App\Work\Harvest\HarvestReportClient;
use App\Work\Report\Command\GenerateWorkReportCommand;
use App\Work\Report\WorkReport;
use App\Work\Report\WorkReportBuilder;
use App\Work\Report\WorkReportException;
use App\Work\Report\WorkReportFacade;
use App\Work\Report\WorkReportPeriod;
use App\Work\Report\WorkReportSanitizer;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateWorkReportCommandTest extends TestCase
{

	public function testInvalidRangeDoesNotStartDownload(): void
	{
		$facade = $this->createMock(WorkReportFacade::class);
		$facade->expects(self::never())->method('generate');
		$tester = $this->tester($facade);
		self::assertSame(2, $tester->execute(['--from' => '2026-10-23']));
		self::assertStringContainsString('Provide both --from and --to', $tester->getDisplay());
	}

	public function testUsesPragueTodayAndExportsTheSavedSnapshot(): void
	{
		$period = WorkReportPeriod::create('2026-10-27');
		$report = $this->report($period);
		$facade = $this->createMock(WorkReportFacade::class);
		$facade->expects(self::once())->method('generate')->with(self::callback(
			static fn (WorkReportPeriod $actual): bool => $actual->reportDate->format('Y-m-d') === '2026-10-27'
				&& $actual->from->format('Y-m-d') === '2026-10-26',
		), true)->willReturn($report);
		$path = sys_get_temp_dir() . '/work-report-' . $report->getId()->toString() . '.json';
		try {
			$tester = $this->tester($facade);
			self::assertSame(0, $tester->execute(['--output' => $path]));
			self::assertSame($report->getData()['period'], Json::decode(FileSystem::read($path), true)['period']);
			self::assertSame(0o600, fileperms($path) & 0o777);
			self::assertStringContainsString('0 entries, 0 projects', $tester->getDisplay());
		} finally {
			FileSystem::delete($path);
		}
	}

	public function testPartialReportReturnsFailureAfterSavingAndExporting(): void
	{
		$period = WorkReportPeriod::create('2026-10-26');
		$report = $this->report($period);
		$data = $report->getData();
		$data['status'] = 'partial';
		$data['warnings'] = ['A GitLab item could not be loaded.'];
		$report->update($data, new ImmutableDateTime());
		$facade = $this->createStub(WorkReportFacade::class);
		$facade->method('generate')->willReturn($report);
		$tester = $this->tester($facade);
		self::assertSame(1, $tester->execute(['--date' => '2026-10-26']));
		self::assertStringContainsString('2026-10-23 to 2026-10-25', $tester->getDisplay());
		self::assertStringContainsString('The saved report is partial', $tester->getDisplay());
	}

	public function testMissingConfigurationIsReportedWithoutStackTrace(): void
	{
		$facade = $this->createStub(WorkReportFacade::class);
		$facade->method('generate')->willThrowException(
			new WorkReportException('Configure parameters.workReport.gitlabAccessToken.'),
		);
		$tester = $this->tester($facade);
		self::assertSame(1, $tester->execute([]));
		self::assertStringContainsString('Configure parameters.workReport.gitlabAccessToken.', $tester->getDisplay());
	}

	public function testSummaryOnlyUsesSavedPeriodWithoutDownloadingSources(): void
	{
		$report = $this->report(WorkReportPeriod::create('2026-10-26', '2026-10-22', '2026-10-25'));
		$facade = $this->createMock(WorkReportFacade::class);
		$facade->expects(self::never())->method('generate');
		$facade->expects(self::once())->method('summarize')->with(self::callback(
			static fn (ImmutableDateTime $date): bool => $date->format('Y-m-d') === '2026-10-26',
		))->willReturn($report);
		$tester = $this->tester($facade);
		self::assertSame(0, $tester->execute(['--date' => '2026-10-26', '--summary-only' => true]));
		self::assertStringContainsString('2026-10-22 to 2026-10-25', $tester->getDisplay());
	}

	public function testNoAiDisablesTheSupplementAndConflictingOptionsFailBeforeAnyWork(): void
	{
		$report = $this->report(WorkReportPeriod::create('2026-10-27'));
		$facade = $this->createMock(WorkReportFacade::class);
		$facade->expects(self::once())->method('generate')->with(self::anything(), false)->willReturn($report);
		$facade->expects(self::never())->method('summarize');
		$tester = $this->tester($facade);
		self::assertSame(0, $tester->execute(['--no-ai' => true]));
		self::assertSame(2, $tester->execute(['--summary-only' => true, '--no-ai' => true]));
		self::assertSame(2, $tester->execute(['--summary-only' => true, '--from' => '2026-10-23']));
	}

	public function testAiFailureDoesNotFailASuccessfulSourceReport(): void
	{
		$report = $this->report(WorkReportPeriod::create('2026-10-27'));
		$data = $report->getData();
		$data['aiSummary'] = [
			'status' => 'failed', 'model' => 'gpt-6-luna', 'generatedAt' => '2026-10-27T07:00:00+01:00',
			'bullets' => [], 'error' => 'AI summary generation failed.',
		];
		$report->update($data, new ImmutableDateTime());
		$facade = $this->createStub(WorkReportFacade::class);
		$facade->method('generate')->willReturn($report);
		$tester = $this->tester($facade);
		self::assertSame(0, $tester->execute([]));
		self::assertStringContainsString('AI summary failed.', $tester->getDisplay());
	}

	private function tester(WorkReportFacade $facade): CommandTester
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-10-26T23:30:00+00:00'));
		return new CommandTester(new GenerateWorkReportCommand($facade, $clock));
	}

	private function report(WorkReportPeriod $period): WorkReport
	{
		$harvest = $this->createStub(HarvestReportClient::class);
		$harvest->method('getEntries')->willReturn([]);
		$builder = new WorkReportBuilder(
			$harvest,
			$this->createStub(GitLabReportClient::class),
			new GitLabReferenceExtractor(),
			new WorkReportSanitizer('synthetic', null),
			new NullLogger(),
		);
		$now = new ImmutableDateTime('2026-10-27T07:00:00+01:00');
		return new WorkReport(
			$this->createStub(AppAdmin::class),
			$period->reportDate,
			$builder->build($period, $now),
			$now,
		);
	}

}
