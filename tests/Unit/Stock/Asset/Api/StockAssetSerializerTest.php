<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Api;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Api\StockAssetSerializer;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
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

	private function createSerializer(ImmutableDateTime $now): StockAssetSerializer
	{
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		return new StockAssetSerializer($datetimeFactory);
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
