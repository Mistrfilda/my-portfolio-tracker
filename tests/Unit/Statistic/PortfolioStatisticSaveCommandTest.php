<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Admin\AppAdmin;
use App\Admin\LegacyPortfolioOwnerProvider;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\PortfolioStatisticFacade;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticSaveCommand;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

class PortfolioStatisticSaveCommandTest extends TestCase
{

	private AppAdmin $appAdmin;

	protected function setUp(): void
	{
		parent::setUp();
		$this->appAdmin = $this->createStub(AppAdmin::class);
		$this->appAdmin->method('getId')->willReturn(Uuid::uuid4());
	}

	public function testSavesSnapshotRebuildsPerformanceAndAppendsFreshValues(): void
	{
		$steps = [];
		$record = new PortfolioStatisticRecord($this->appAdmin, new ImmutableDateTime('2026-07-23 12:00:00'));
		$statisticFacade = $this->createMock(PortfolioStatisticFacade::class);
		$statisticFacade->expects(self::once())
			->method('saveCurrentDashboardValues')
			->with($this->appAdmin, false)
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

		$ownerProvider = $this->createStub(LegacyPortfolioOwnerProvider::class);
		$ownerProvider->method('getOwner')->willReturn($this->appAdmin);
		$tester = new CommandTester(new PortfolioStatisticSaveCommand(
			$statisticFacade,
			$performanceRebuildFacade,
			$ownerProvider,
		));

		self::assertSame(0, $tester->execute([]));
		self::assertSame(['save', 'rebuild', 'append'], $steps);
		self::assertStringContainsString('Rebuilt 12 portfolio performance months', $tester->getDisplay());
	}

}
