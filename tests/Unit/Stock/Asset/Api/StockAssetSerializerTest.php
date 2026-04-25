<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Api;

use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\Api\StockAssetSerializer;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\StockValuationPriceProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
class StockAssetSerializerTest extends TestCase
{

	public function testSerializeUsesPreviousTradingDayOnSaturday(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(new ImmutableDateTime('2026-01-10 09:00:00'));

		$data = $serializer->serialize($stockAsset);

		self::assertSame(10.0, $data['oneDayChange']);
	}

	public function testSerializeUsesPreviousTradingDayOnMondayBeforeMarketOpen(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(new ImmutableDateTime('2026-01-12 08:00:00'));

		$data = $serializer->serialize($stockAsset);

		self::assertSame(10.0, $data['oneDayChange']);
	}

	public function testSerializeContainsValuationPrices(): void
	{
		$stockAsset = $this->createStockAssetWithCurrentFridayPrice();
		$serializer = $this->createSerializer(
			new ImmutableDateTime('2026-01-12 08:00:00'),
			10.5,
			11.5,
			12.5,
		);

		$data = $serializer->serialize($stockAsset);

		self::assertSame(['price' => 10.5, 'currency' => CurrencyEnum::USD->value], $data['priceFromAllModels']);
		self::assertSame(['price' => 11.5, 'currency' => CurrencyEnum::USD->value], $data['analyticsPrice']);
		self::assertSame(['price' => 12.5, 'currency' => CurrencyEnum::USD->value], $data['aiAnalysisPrice']);
	}

	private function createSerializer(
		ImmutableDateTime $now,
		float|null $priceFromAllModels = null,
		float|null $analyticsPrice = null,
		float|null $aiAnalysisPrice = null,
	): StockAssetSerializer
	{
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$stockValuationPriceProvider = $this->createMock(StockValuationPriceProvider::class);
		$stockValuationPriceProvider->method('getAverageModelPrice')->willReturn(
			$this->createAssetPrice($priceFromAllModels),
		);
		$stockValuationPriceProvider->method('getAnalyticsPrice')->willReturn(
			$this->createAssetPrice($analyticsPrice),
		);
		$stockValuationPriceProvider->method('getAiAnalysisPrice')->willReturn(
			$this->createAssetPrice($aiAnalysisPrice),
		);

		return new StockAssetSerializer($datetimeFactory, $stockValuationPriceProvider);
	}

	private function createAssetPrice(float|null $price): AssetPrice|null
	{
		if ($price === null) {
			return null;
		}

		$stockAsset = $this->createMock(StockAsset::class);
		$stockAsset->method('getCurrency')->willReturn(CurrencyEnum::USD);

		return new AssetPrice($stockAsset, $price, CurrencyEnum::USD);
	}

	private function createStockAssetWithCurrentFridayPrice(): StockAsset
	{
		$stockAsset = new StockAsset(
			'Apple Inc.',
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			'AAPL',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			new ImmutableDateTime('2026-01-09 22:00:00'),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);

		$thursdayPriceRecord = new StockAssetPriceRecord(
			new ImmutableDateTime('2026-01-08'),
			CurrencyEnum::USD,
			100.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			new ImmutableDateTime('2026-01-08 22:00:00'),
		);

		$fridayPriceRecord = new StockAssetPriceRecord(
			new ImmutableDateTime('2026-01-09'),
			CurrencyEnum::USD,
			110.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			new ImmutableDateTime('2026-01-09 22:00:00'),
		);

		$priceRecordsReflection = new ReflectionProperty($stockAsset, 'priceRecords');
		$priceRecordsReflection->setValue($stockAsset, new ArrayCollection([$thursdayPriceRecord, $fridayPriceRecord]));

		$stockAsset->setCurrentPrice($fridayPriceRecord, new ImmutableDateTime('2026-01-09 22:00:00'));

		return $stockAsset;
	}

}
