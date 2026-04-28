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
			new AssetPrice($stockAsset, 120.0, CurrencyEnum::USD),
			new AssetPrice($stockAsset, 124.0, CurrencyEnum::USD),
			new AssetPrice($stockAsset, 116.0, CurrencyEnum::USD),
		);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNDERVALUED, $marginOfSafety->getStatus());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::HIGH, $marginOfSafety->getConfidence());
		self::assertSame(120.0, $marginOfSafety->getFairPriceEstimate()?->getPrice());
		self::assertSame(20.0, $marginOfSafety->getMarginPercentage());
		self::assertEqualsWithDelta(6.67, $marginOfSafety->getSourceSpreadPercentage() ?? 0.0, 0.01);
		self::assertSame(3, $marginOfSafety->getSourcesCount());
	}

	public function testReturnsUnknownSignalWhenComparablePriceSourcesAreMissing(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset($stockAsset, null, null, null);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNKNOWN, $marginOfSafety->getStatus());
		self::assertSame(StockValuationMarginOfSafetyConfidenceEnum::UNKNOWN, $marginOfSafety->getConfidence());
		self::assertNull($marginOfSafety->getFairPriceEstimate());
		self::assertSame(['No comparable fair price sources are available.'], $marginOfSafety->getReasons());
	}

	public function testIgnoresPriceSourcesInDifferentCurrency(): void
	{
		$stockAsset = $this->createStockAsset(100.0, CurrencyEnum::USD);
		$provider = new StockValuationMarginOfSafetyProvider();

		$marginOfSafety = $provider->getForStockAsset(
			$stockAsset,
			new AssetPrice($stockAsset, 110.0, CurrencyEnum::CZK),
			null,
			null,
		);

		self::assertSame(StockValuationMarginOfSafetyStatusEnum::UNKNOWN, $marginOfSafety->getStatus());
		self::assertSame(1, count($marginOfSafety->getReasons()));
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
