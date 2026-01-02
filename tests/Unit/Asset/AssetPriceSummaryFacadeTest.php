<?php

declare(strict_types = 1);

namespace App\Test\Unit\Asset;

use App\Asset\Price\AssetPriceFacade;
use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\Test\Unit\Currency\CurrencyConversionHelper;
use App\Test\UpdatedTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class AssetPriceSummaryFacadeTest extends UpdatedTestCase
{

	public function testGetTotalInvestedAmountWithSingleFacade(): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 5));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getTotalInvestedAmount(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrency());
		self::assertEquals(10000.0, $result->getPrice());
		self::assertEquals(5, $result->getCounter());
	}

	public function testGetTotalInvestedAmountWithMultipleFacades(): void
	{
		$assetPriceFacadeMock1 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock1
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock1
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 5));

		$assetPriceFacadeMock2 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock2
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock2
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 5000.0, 3));

		$assetPriceFacadeMock3 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock3
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock3
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 2500.0, 2));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock1, $assetPriceFacadeMock2, $assetPriceFacadeMock3],
			$summaryPriceService,
		);

		$result = $facade->getTotalInvestedAmount(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrency());
		self::assertEquals(17500.0, $result->getPrice());
		self::assertEquals(10, $result->getCounter());
	}

	public function testGetTotalInvestedAmountExcludesFacadesNotIncludedInTotals(): void
	{
		$assetPriceFacadeMock1 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock1
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock1
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 5));

		$assetPriceFacadeMock2 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock2
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(false);
		// getTotalInvestedAmountSummaryPrice should NOT be called

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock1, $assetPriceFacadeMock2],
			$summaryPriceService,
		);

		$result = $facade->getTotalInvestedAmount(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrency());
		self::assertEquals(10000.0, $result->getPrice());
		self::assertEquals(5, $result->getCounter());
	}

	public function testGetTotalInvestedAmountWithNoFacades(): void
	{
		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[],
			$summaryPriceService,
		);

		$result = $facade->getTotalInvestedAmount(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrency());
		self::assertEquals(0.0, $result->getPrice());
		self::assertEquals(0, $result->getCounter());
	}

	public function testGetCurrentValueWithSingleFacade(): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->once()
			->with(CurrencyEnum::EUR)
			->andReturn(new SummaryPrice(CurrencyEnum::EUR, 5000.0, 10));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getCurrentValue(CurrencyEnum::EUR);

		self::assertEquals(CurrencyEnum::EUR, $result->getCurrency());
		self::assertEquals(5000.0, $result->getPrice());
		self::assertEquals(10, $result->getCounter());
	}

	public function testGetCurrentValueWithMultipleFacades(): void
	{
		$assetPriceFacadeMock1 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock1
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock1
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->once()
			->with(CurrencyEnum::USD)
			->andReturn(new SummaryPrice(CurrencyEnum::USD, 15000.0, 8));

		$assetPriceFacadeMock2 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock2
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock2
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->once()
			->with(CurrencyEnum::USD)
			->andReturn(new SummaryPrice(CurrencyEnum::USD, 3000.0, 2));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock1, $assetPriceFacadeMock2],
			$summaryPriceService,
		);

		$result = $facade->getCurrentValue(CurrencyEnum::USD);

		self::assertEquals(CurrencyEnum::USD, $result->getCurrency());
		self::assertEquals(18000.0, $result->getPrice());
		self::assertEquals(10, $result->getCounter());
	}

	public function testGetCurrentValueExcludesFacadesNotIncludedInTotals(): void
	{
		$assetPriceFacadeMock1 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock1
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(false);
		// getCurrentPortfolioValueSummaryPrice should NOT be called

		$assetPriceFacadeMock2 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock2
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock2
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 25000.0, 15));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock1, $assetPriceFacadeMock2],
			$summaryPriceService,
		);

		$result = $facade->getCurrentValue(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrency());
		self::assertEquals(25000.0, $result->getPrice());
		self::assertEquals(15, $result->getCounter());
	}

	public function testGetTotalPriceDiffPositiveGain(): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 15000.0, 5));
		$assetPriceFacadeMock
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 5));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getTotalPriceDiff(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrencyEnum());
		self::assertEquals(5000.0, $result->getPriceDifference());
		// 15000 / 10000 * 100 = 150, getPercentageDifference returns raw - 100
		self::assertEquals(50.0, $result->getPercentageDifference());
	}

	public function testGetTotalPriceDiffNegativeLoss(): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 8000.0, 5));
		$assetPriceFacadeMock
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 5));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getTotalPriceDiff(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrencyEnum());
		self::assertEquals(-2000.0, $result->getPriceDifference());
		// 8000 / 10000 * 100 = 80, getPercentageDifference returns raw - 100
		self::assertEquals(-20.0, $result->getPercentageDifference());
	}

	public function testGetTotalPriceDiffWithMultipleFacades(): void
	{
		$assetPriceFacadeMock1 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock1
			->shouldReceive('includeToTotalValues')
			->andReturn(true);
		$assetPriceFacadeMock1
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 12000.0, 3));
		$assetPriceFacadeMock1
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 10000.0, 3));

		$assetPriceFacadeMock2 = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock2
			->shouldReceive('includeToTotalValues')
			->andReturn(true);
		$assetPriceFacadeMock2
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 4500.0, 2));
		$assetPriceFacadeMock2
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 5000.0, 2));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock1, $assetPriceFacadeMock2],
			$summaryPriceService,
		);

		$result = $facade->getTotalPriceDiff(CurrencyEnum::CZK);

		// Current: 12000 + 4500 = 16500
		// Invested: 10000 + 5000 = 15000
		// Diff: 16500 - 15000 = 1500
		self::assertEquals(CurrencyEnum::CZK, $result->getCurrencyEnum());
		self::assertEquals(1500.0, $result->getPriceDifference());
		// 16500 / 15000 * 100 = 110, getPercentageDifference returns raw - 100
		self::assertEquals(10.0, $result->getPercentageDifference());
	}

	public function testGetTotalPriceDiffWithZeroInvested(): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getCurrentPortfolioValueSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 5000.0, 5));
		$assetPriceFacadeMock
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->with(CurrencyEnum::CZK)
			->andReturn(new SummaryPrice(CurrencyEnum::CZK, 0.0, 0));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getTotalPriceDiff(CurrencyEnum::CZK);

		self::assertEquals(CurrencyEnum::CZK, $result->getCurrencyEnum());
		self::assertEquals(5000.0, $result->getPriceDifference());
		// When invested is 0, percentageDiff is set to 200 (raw), so getPercentageDifference returns 100
		self::assertEquals(100.0, $result->getPercentageDifference());
	}

	#[DataProvider('provideDifferentCurrencies')]
	public function testGetTotalInvestedAmountInDifferentCurrencies(CurrencyEnum $currency): void
	{
		$assetPriceFacadeMock = Mockery::mock(AssetPriceFacade::class);
		$assetPriceFacadeMock
			->shouldReceive('includeToTotalValues')
			->once()
			->andReturn(true);
		$assetPriceFacadeMock
			->shouldReceive('getTotalInvestedAmountSummaryPrice')
			->once()
			->with($currency)
			->andReturn(new SummaryPrice($currency, 1000.0, 1));

		$summaryPriceService = new SummaryPriceService(
			CurrencyConversionHelper::getCurrencyConversionFacade(),
		);

		$facade = new AssetPriceSummaryFacade(
			[$assetPriceFacadeMock],
			$summaryPriceService,
		);

		$result = $facade->getTotalInvestedAmount($currency);

		self::assertEquals($currency, $result->getCurrency());
		self::assertEquals(1000.0, $result->getPrice());
	}

	/**
	 * @return array<string, array<CurrencyEnum>>
	 */
	public static function provideDifferentCurrencies(): array
	{
		return [
			'CZK' => [CurrencyEnum::CZK],
			'EUR' => [CurrencyEnum::EUR],
			'USD' => [CurrencyEnum::USD],
		];
	}

}
