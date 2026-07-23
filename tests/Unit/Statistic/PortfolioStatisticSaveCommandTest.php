<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\PortfolioStatisticFacade;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticSaveCommand;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PortfolioStatisticSaveCommandTest extends TestCase
{

	public function testSavesSnapshotRebuildsPerformanceAndAppendsFreshValues(): void
	{
		$steps = [];
		$record = new PortfolioStatisticRecord(new ImmutableDateTime('2026-07-23 12:00:00'));
		$statisticFacade = $this->createMock(PortfolioStatisticFacade::class);
		$statisticFacade->expects(self::once())
			->method('saveCurrentDashboardValues')
			->with(false)
			->willReturnCallback(static function () use (&$steps, $record): PortfolioStatisticRecord {
				$steps[] = 'save';
				return $record;
			});
		$performanceRebuildFacade = $this->createMock(PortfolioPerformanceRebuildFacade::class);
		$performanceRebuildFacade->expects(self::once())
			->method('rebuild')
			->willReturnCallback(static function () use (&$steps): int {
				$steps[] = 'rebuild';
				return 12;
			});
		$statisticFacade->expects(self::once())
			->method('appendPortfolioPerformanceValues')
			->with($record)
			->willReturnCallback(static function () use (&$steps): void {
				$steps[] = 'append';
			});

		$tester = new CommandTester(new PortfolioStatisticSaveCommand(
			$statisticFacade,
			$performanceRebuildFacade,
		));

		self::assertSame(0, $tester->execute([]));
		self::assertSame(['save', 'rebuild', 'append'], $steps);
		self::assertStringContainsString('Rebuilt 12 portfolio performance months', $tester->getDisplay());
	}

}
