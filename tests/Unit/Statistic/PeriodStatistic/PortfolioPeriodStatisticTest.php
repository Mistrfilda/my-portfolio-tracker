<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\PeriodStatistic;

use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartPointDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticStatusEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPeriodStatisticTest extends TestCase
{

	public function testCompleteStoresTypedSections(): void
	{
		$start = new ImmutableDateTime('2026-01-01 00:00:00');
		$end = new ImmutableDateTime('2026-01-31 23:59:59');
		$now = new ImmutableDateTime('2026-02-01 12:00:00');
		$report = new PortfolioPeriodStatistic($start, $end, $now);

		$summary = new PortfolioPeriodStatisticSummaryDTO(
			100.0,
			150.0,
			50.0,
			120.0,
			190.0,
			70.0,
			58.33,
			20.0,
			5.0,
			3.0,
			28.0,
			12.0,
			null,
			11.0,
			10.0,
			['warning'],
			true,
		);
		$assets = new PortfolioPeriodStatisticAssetSectionDTO([
			new PortfolioPeriodStatisticAssetDTO(
				'asset-id',
				'stock',
				'Example',
				'EXM',
				'USD',
				'2026-01-01',
				'2026-01-31',
				10.0,
				12.0,
				20.0,
				100.0,
				120.0,
				0.0,
				0.0,
				20.0,
				3.0,
				23.0,
			),
		]);
		$dividends = new PortfolioPeriodStatisticDividendSectionDTO(
			1,
			4.0,
			3.0,
			1.0,
			[
				new PortfolioPeriodStatisticDividendDTO(
					'record-id',
					'asset-id',
					'Example',
					'EXM',
					'2026-01-15',
					null,
					'Regular',
					1,
					'USD',
					4.0,
					3.0,
					4.0,
					3.0,
				),
			],
		);
		$charts = new PortfolioPeriodStatisticChartSectionDTO(
			[new PortfolioPeriodStatisticChartPointDTO('2026-01-01', 120.0)],
			[new PortfolioPeriodStatisticChartPointDTO('2026-01-01', 100.0)],
			[new PortfolioPeriodStatisticChartPointDTO('Example (EXM)', 3.0)],
		);

		$report->markProcessing($now);
		$report->complete($start, $end, $summary, $assets, $dividends, $charts, $now);

		self::assertSame(PortfolioPeriodStatisticStatusEnum::COMPLETED, $report->getStatus());
		self::assertSame(28.0, $report->getSummary()?->totalPeriodProfit);
		self::assertSame('Example', $report->getAssetSection()?->assets[0]->name);
		self::assertSame('2026-01-15', $report->getDividendSection()?->dividends[0]->exDate);
		self::assertSame(120.0, $report->getChartSection()?->portfolioValues[0]->value);
	}

	public function testFailedReportCanBeQueuedAgain(): void
	{
		$now = new ImmutableDateTime('2026-02-01 12:00:00');
		$report = new PortfolioPeriodStatistic(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			$now,
		);

		$report->markFailed('Failure', $now);
		self::assertTrue($report->canRetry());
		self::assertSame('Failure', $report->getProcessingError());

		$report->markQueued($now);
		self::assertTrue($report->isPending());
		self::assertNull($report->getProcessingError());
	}

}
