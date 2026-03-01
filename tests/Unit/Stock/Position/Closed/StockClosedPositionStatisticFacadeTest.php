<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position\Closed;

use App\Asset\Asset;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use App\Stock\Position\Closed\StockClosedPositionStatisticFacade;
use App\Stock\Position\StockPosition;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockClosedPositionStatisticFacadeTest extends TestCase
{

	private StockClosedPositionStatisticFacade $facade;

	private StockClosedPositionRepository $stockClosedPositionRepository;

	private CurrencyConversionFacade $currencyConversionFacade;

	public function setUp(): void
	{
		$this->stockClosedPositionRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockClosedPositionRepository::class,
		);
		$this->currencyConversionFacade = UpdatedTestCase::createMockWithIgnoreMethods(
			CurrencyConversionFacade::class,
		);

		$this->facade = new StockClosedPositionStatisticFacade(
			$this->stockClosedPositionRepository,
			$this->currencyConversionFacade,
		);
	}

	public function testCalculateProfitInPeriodNoPositions(): void
	{
		$start = new ImmutableDateTime('2025-01-01');
		$end = new ImmutableDateTime('2025-12-31');

		$this->stockClosedPositionRepository->shouldReceive('findBetweenDates')
			->with($start, $end)
			->once()
			->andReturn([]);

		$result = $this->facade->calculateProfitInPeriod($start, $end);

		$this->assertSame(0.0, $result);
	}

	public function testCalculateProfitInPeriodSinglePosition(): void
	{
		$start = new ImmutableDateTime('2025-01-01');
		$end = new ImmutableDateTime('2025-12-31');
		$buyDate = new ImmutableDateTime('2025-03-01');
		$sellDate = new ImmutableDateTime('2025-06-01');

		$asset = UpdatedTestCase::createMockWithIgnoreMethods(Asset::class);

		$sellPrice = new AssetPrice($asset, 15000.0, CurrencyEnum::CZK);
		$buyPrice = new AssetPrice($asset, 10000.0, CurrencyEnum::CZK);

		$sellPriceCzk = new AssetPrice($asset, 15000.0, CurrencyEnum::CZK);
		$buyPriceCzk = new AssetPrice($asset, 10000.0, CurrencyEnum::CZK);

		$stockPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$stockPosition->shouldReceive('getTotalInvestedAmountInBrokerCurrency')
			->andReturn($buyPrice);
		$stockPosition->shouldReceive('getOrderDate')
			->andReturn($buyDate);

		$closedPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$closedPosition->shouldReceive('getTotalCloseAmountInBrokerCurrency')
			->andReturn($sellPrice);
		$closedPosition->shouldReceive('getAssetPositon')
			->andReturn($stockPosition);
		$closedPosition->shouldReceive('getDate')
			->andReturn($sellDate);

		$this->stockClosedPositionRepository->shouldReceive('findBetweenDates')
			->with($start, $end)
			->once()
			->andReturn([$closedPosition]);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($sellPrice, CurrencyEnum::CZK, $sellDate)
			->once()
			->andReturn($sellPriceCzk);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($buyPrice, CurrencyEnum::CZK, $buyDate)
			->once()
			->andReturn($buyPriceCzk);

		$result = $this->facade->calculateProfitInPeriod($start, $end);

		$this->assertSame(5000.0, $result);
	}

	public function testCalculateProfitInPeriodMultiplePositions(): void
	{
		$start = new ImmutableDateTime('2025-01-01');
		$end = new ImmutableDateTime('2025-12-31');

		$asset = UpdatedTestCase::createMockWithIgnoreMethods(Asset::class);

		// Position 1: profit 5000 CZK
		$sellDate1 = new ImmutableDateTime('2025-04-01');
		$buyDate1 = new ImmutableDateTime('2025-02-01');
		$sellPrice1 = new AssetPrice($asset, 15000.0, CurrencyEnum::CZK);
		$buyPrice1 = new AssetPrice($asset, 10000.0, CurrencyEnum::CZK);

		$stockPosition1 = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$stockPosition1->shouldReceive('getTotalInvestedAmountInBrokerCurrency')->andReturn($buyPrice1);
		$stockPosition1->shouldReceive('getOrderDate')->andReturn($buyDate1);

		$closedPosition1 = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$closedPosition1->shouldReceive('getTotalCloseAmountInBrokerCurrency')->andReturn($sellPrice1);
		$closedPosition1->shouldReceive('getAssetPositon')->andReturn($stockPosition1);
		$closedPosition1->shouldReceive('getDate')->andReturn($sellDate1);

		// Position 2: loss 2000 CZK
		$sellDate2 = new ImmutableDateTime('2025-05-01');
		$buyDate2 = new ImmutableDateTime('2025-03-01');
		$sellPrice2 = new AssetPrice($asset, 8000.0, CurrencyEnum::CZK);
		$buyPrice2 = new AssetPrice($asset, 10000.0, CurrencyEnum::CZK);

		$stockPosition2 = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$stockPosition2->shouldReceive('getTotalInvestedAmountInBrokerCurrency')->andReturn($buyPrice2);
		$stockPosition2->shouldReceive('getOrderDate')->andReturn($buyDate2);

		$closedPosition2 = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$closedPosition2->shouldReceive('getTotalCloseAmountInBrokerCurrency')->andReturn($sellPrice2);
		$closedPosition2->shouldReceive('getAssetPositon')->andReturn($stockPosition2);
		$closedPosition2->shouldReceive('getDate')->andReturn($sellDate2);

		$this->stockClosedPositionRepository->shouldReceive('findBetweenDates')
			->with($start, $end)
			->once()
			->andReturn([$closedPosition1, $closedPosition2]);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($sellPrice1, CurrencyEnum::CZK, $sellDate1)
			->once()
			->andReturn(new AssetPrice($asset, 15000.0, CurrencyEnum::CZK));

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($buyPrice1, CurrencyEnum::CZK, $buyDate1)
			->once()
			->andReturn(new AssetPrice($asset, 10000.0, CurrencyEnum::CZK));

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($sellPrice2, CurrencyEnum::CZK, $sellDate2)
			->once()
			->andReturn(new AssetPrice($asset, 8000.0, CurrencyEnum::CZK));

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($buyPrice2, CurrencyEnum::CZK, $buyDate2)
			->once()
			->andReturn(new AssetPrice($asset, 10000.0, CurrencyEnum::CZK));

		$result = $this->facade->calculateProfitInPeriod($start, $end);

		// 5000 - 2000 = 3000
		$this->assertSame(3000.0, $result);
	}

	public function testCalculateProfitInPeriodWithCurrencyConversion(): void
	{
		$start = new ImmutableDateTime('2025-01-01');
		$end = new ImmutableDateTime('2025-12-31');
		$buyDate = new ImmutableDateTime('2025-02-01');
		$sellDate = new ImmutableDateTime('2025-06-01');

		$asset = UpdatedTestCase::createMockWithIgnoreMethods(Asset::class);

		// USD position - sell at 100 USD, buy at 80 USD, converted to CZK
		$sellPriceUsd = new AssetPrice($asset, 100.0, CurrencyEnum::USD);
		$buyPriceUsd = new AssetPrice($asset, 80.0, CurrencyEnum::USD);

		$sellPriceCzk = new AssetPrice($asset, 2300.0, CurrencyEnum::CZK);
		$buyPriceCzk = new AssetPrice($asset, 1760.0, CurrencyEnum::CZK);

		$stockPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$stockPosition->shouldReceive('getTotalInvestedAmountInBrokerCurrency')->andReturn($buyPriceUsd);
		$stockPosition->shouldReceive('getOrderDate')->andReturn($buyDate);

		$closedPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockClosedPosition::class);
		$closedPosition->shouldReceive('getTotalCloseAmountInBrokerCurrency')->andReturn($sellPriceUsd);
		$closedPosition->shouldReceive('getAssetPositon')->andReturn($stockPosition);
		$closedPosition->shouldReceive('getDate')->andReturn($sellDate);

		$this->stockClosedPositionRepository->shouldReceive('findBetweenDates')
			->with($start, $end)
			->once()
			->andReturn([$closedPosition]);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($sellPriceUsd, CurrencyEnum::CZK, $sellDate)
			->once()
			->andReturn($sellPriceCzk);

		$this->currencyConversionFacade->shouldReceive('getConvertedAssetPrice')
			->with($buyPriceUsd, CurrencyEnum::CZK, $buyDate)
			->once()
			->andReturn($buyPriceCzk);

		$result = $this->facade->calculateProfitInPeriod($start, $end);

		// 2300 - 1760 = 540
		$this->assertSame(540.0, $result);
	}

}
