<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Download;

use App\Stock\Asset\Download\StockAssetDataFreshness;
use App\Stock\Asset\Download\StockAssetDataMonitoring;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\System\Resolver\SystemValueDatabaseResolver;
use App\System\Resolver\SystemValueStockDataCountResolver;
use App\System\SystemValueEnum;
use App\System\SystemValueFacade;
use App\System\SystemValueRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StockAssetDataMonitoringTest extends TestCase
{

	public function testHealthUsesFreshnessAndNewStockGrace(): void
	{
		$at = new ImmutableDateTime('2026-09-24 13:30:00');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($at);
		$assets = $this->createMock(StockAssetRepository::class);
		$assets->expects($this->exactly(2))->method('countForDataType')
			->with(StockAssetDataType::DIVIDENDS, new ImmutableDateTime('2026-09-23 06:00:00'), null)
			->willReturnOnConsecutiveCalls(20, 21);
		$assets->expects($this->exactly(2))->method('countStaleForDataType')
			->with(
				StockAssetDataType::DIVIDENDS,
				new ImmutableDateTime('2026-09-23 06:00:00'),
				$at->modify('-15 minutes'),
				null,
			)
			->willReturnOnConsecutiveCalls(0, 1);
		$monitor = new StockAssetDataMonitoring(
			$assets,
			new StockAssetDataFreshness($clock, []),
			$this->createStub(SystemValueRepository::class),
			$this->createStub(SystemValueFacade::class),
			$clock,
		);
		$this->assertSame(20, $monitor->getFreshCount(StockAssetDataType::DIVIDENDS));
		$this->assertTrue($monitor->isHealthy(StockAssetDataType::DIVIDENDS));
		$this->assertFalse($monitor->isHealthy(StockAssetDataType::DIVIDENDS));
		$this->assertSame(21, $monitor->getFreshCount(StockAssetDataType::DIVIDENDS));
	}

	public function testActivationRejectsIncompleteBootstrap(): void
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-09-24 13:30:00'));
		$assets = $this->createStub(StockAssetRepository::class);
		$assets->method('countForDataType')->willReturnCallback(
			static fn (StockAssetDataType $type, ImmutableDateTime|null $since = null): int => $since === null ? 21 : 0,
		);
		$values = $this->createMock(SystemValueFacade::class);
		$values->expects($this->never())->method('updateValue');
		$monitor = new StockAssetDataMonitoring(
			$assets,
			new StockAssetDataFreshness($clock, ['TWELVE_DATA' => 2, 'WEB_SCRAP' => 1, 'PSE' => 1]),
			$this->createStub(SystemValueRepository::class),
			$values,
			$clock,
		);
		$this->expectException(RuntimeException::class);
		$monitor->enable();
	}

	public function testActivationRequiresAllTypesAndPersistsSwitch(): void
	{
		$at = new ImmutableDateTime('2026-09-24 13:30:00');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($at);
		$assets = $this->createMock(StockAssetRepository::class);
		$assets->expects($this->exactly(10))->method('countForDataType')->willReturnCallback(
			static fn (StockAssetDataType $type, ImmutableDateTime|null $since = null,
				StockAssetPriceDownloaderEnum|null $source = null,): int => $source !== null ? 7 : 21,
		);
		$values = $this->createMock(SystemValueFacade::class);
		$values->expects($this->once())->method('updateValue')
			->with(SystemValueEnum::STOCK_DATA_MONITORING_ENABLED_AT, $at, null, null);
		$monitor = new StockAssetDataMonitoring(
			$assets,
			new StockAssetDataFreshness($clock, ['TWELVE_DATA' => 2, 'WEB_SCRAP' => 1, 'PSE' => 1]),
			$this->createStub(SystemValueRepository::class),
			$values,
			$clock,
		);
		$monitor->enable();
	}

	public function testStatisticsKeepLegacyValuesUntilActivated(): void
	{
		$monitor = $this->createMock(StockAssetDataMonitoring::class);
		$monitor->expects($this->exactly(2))->method('isEnabled')->willReturnOnConsecutiveCalls(false, true);
		$monitor->expects($this->once())->method('getFreshCount')->with(StockAssetDataType::DIVIDENDS)->willReturn(21);
		$legacy = $this->createMock(SystemValueDatabaseResolver::class);
		$legacy->expects($this->once())->method('getValueForEnum')->with(
			SystemValueEnum::DIVIDENDS_UPDATED_COUNT,
		)->willReturn(20);
		$resolver = new SystemValueStockDataCountResolver($monitor, $legacy);
		$this->assertSame(20, $resolver->getValueForEnum(SystemValueEnum::DIVIDENDS_UPDATED_COUNT));
		$this->assertSame(21, $resolver->getValueForEnum(SystemValueEnum::DIVIDENDS_UPDATED_COUNT));
	}

}
