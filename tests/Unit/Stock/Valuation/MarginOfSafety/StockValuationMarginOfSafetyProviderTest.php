<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\MarginOfSafety;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyConfidenceEnum;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyStatusEnum;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusConfidenceEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockValuationMarginOfSafetyProviderTest extends TestCase
{

	public function testReturnsUndervaluedHighConfidenceSignalFromAlignedPriceSources(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			$this->createModelConsensus($stockAsset, 120.0),
			new AssetPrice($stockAsset, 124.0, CurrencyEnum::USD),
			new AssetPrice($stockAsset, 116.0, CurrencyEnum::USD),
		);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNDERVALUED, $marginOfSafety->getStatus());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::HIGH, $marginOfSafety->getConfidence());
		self::assertSame(120.0, $marginOfSafety->getFairPriceEstimate()?->getPrice());
		self::assertSame(20.0, $marginOfSafety->getMarginPercentage());
		self::assertSame(120.0, $marginOfSafety->getExternalEstimate()?->getPrice());
		self::assertSame(0.0, $marginOfSafety->getSourceSpreadPercentage());
		self::assertSame(2, $marginOfSafety->getSourceGroupsCount());
		self::assertSame(3, $marginOfSafety->getInputEstimatesCount());
	}

	public function testBalancesModelAndExternalGroupsInsteadOfAveragingAllInputs(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			$this->createModelConsensus($stockAsset, 100.0),
			new AssetPrice($stockAsset, 200.0, CurrencyEnum::USD),
			new AssetPrice($stockAsset, 400.0, CurrencyEnum::USD),
		);

		self::assertSame(300.0, $marginOfSafety->getExternalEstimate()?->getPrice());
		self::assertSame(200.0, $marginOfSafety->getFairPriceEstimate()?->getPrice());
		self::assertSame(100.0, $marginOfSafety->getSourceSpreadPercentage());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::LOW, $marginOfSafety->getConfidence());
	}

	public function testReturnsMediumConfidenceForAlignedGroupsAndMediumModelConsensus(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			$this->createModelConsensus(
				$stockAsset,
				100.0,
				confidence: StockValuationModelConsensusConfidenceEnum::MEDIUM,
			),
			new AssetPrice($stockAsset, 120.0, CurrencyEnum::USD),
			new AssetPrice($stockAsset, 120.0, CurrencyEnum::USD),
		);

		self::assertSame(110.0, $marginOfSafety->getFairPriceEstimate()?->getPrice());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::MEDIUM, $marginOfSafety->getConfidence());
	}

	public function testReturnsUnknownSignalWhenComparablePriceSourcesAreMissing(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			$this->createModelConsensus($stockAsset, null),
			null,
			null,
		);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNKNOWN, $marginOfSafety->getStatus());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::UNKNOWN, $marginOfSafety->getConfidence());
		self::assertNull($marginOfSafety->getFairPriceEstimate());
		self::assertSame(
			['Nejsou dostupné žádné porovnatelné zdroje férové ceny.'],
			$marginOfSafety->getReasons(),
		);
	}

	public function testIgnoresPriceSourcesInDifferentCurrency(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			$this->createModelConsensus($stockAsset, 110.0, CurrencyEnum::CZK),
			null,
			null,
		);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNKNOWN, $marginOfSafety->getStatus());
		self::assertSame(1, count($marginOfSafety->getReasons()));
	}

	private function createModelConsensus(
		StockAsset $stockAsset,
		float|null $price,
		CurrencyEnum $currency = CurrencyEnum::USD,
		StockValuationModelConsensusConfidenceEnum|null $confidence = null,
	): StockValuationModelConsensus
	{
		$consensus = $this->createStub(StockValuationModelConsensus::class);
		$consensus->method('getPrice')->willReturn(
			$price === null ? null : new AssetPrice($stockAsset, $price, $currency),
		);
		$consensus->method('getConfidence')->willReturn(
			$confidence ?? ($price === null
				? StockValuationModelConsensusConfidenceEnum::UNKNOWN
				: StockValuationModelConsensusConfidenceEnum::HIGH),
		);

		return $consensus;
	}

	private function createStockAsset(float $currentPrice, CurrencyEnum $currency): StockAsset
	{
		$now = new ImmutableDateTime('2026-01-01');
		$stockAsset = new StockAsset(
			'Apple Inc.',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'AAPL',
			StockAssetExchange::NASDAQ,
			$currency,
			$now,
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: true,
			watchlist: false,
			industry: null,
		);

		$stockAsset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				$currency,
				$currentPrice,
				$stockAsset,
				StockAssetPriceDownloaderEnum::TWELVE_DATA,
				$now,
			),
			$now,
		);

		return $stockAsset;
	}

}
