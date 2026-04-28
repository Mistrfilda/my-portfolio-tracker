<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Forecast;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Dividend\Forecast\StockAssetDividendForecast;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastCashflowProvider;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecord;
use App\Stock\Dividend\Forecast\StockAssetDividendTrendEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[AllowMockObjectsWithoutExpectations]
class StockAssetDividendForecastCashflowProviderTest extends TestCase
{

	public function testBuildsMonthlyCashflowFromConfirmedAndEstimatedForecastRecords(): void
	{
		$now = new ImmutableDateTime('2026-01-01');
		$stockAsset = $this->createStockAsset('Microsoft', 'MSFT', CurrencyEnum::CZK);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now);
		$record = $this->createRecord(
			$forecast,
			$stockAsset,
			CurrencyEnum::CZK,
			[3, 6, 9, 12],
			[3, 6],
			2.0,
			3.0,
			10,
		);

		$this->setRecords($forecast, [$record]);

		$provider = new StockAssetDividendForecastCashflowProvider($this->createMock(CurrencyConversionFacade::class));
		$months = $provider->getMonths($forecast);

		self::assertCount(12, $months);
		self::assertSame(10.0, $months[2]->getNetAmountInCzk());
		self::assertSame(15.0, $months[2]->getGrossAmountInCzk());
		self::assertSame(1, $months[2]->getConfirmedItemsCount());
		self::assertSame(15.0, $months[8]->getNetAmountInCzk());
		self::assertSame(1, $months[8]->getEstimatedItemsCount());
		self::assertFalse($months[0]->hasItems());
	}

	public function testReturnsZeroCzkAmountWhenCurrencyConversionIsMissing(): void
	{
		$now = new ImmutableDateTime('2026-01-01');
		$stockAsset = $this->createStockAsset('Apple', 'AAPL', CurrencyEnum::USD);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now);
		$record = $this->createRecord(
			$forecast,
			$stockAsset,
			CurrencyEnum::USD,
			[3],
			[],
			0.0,
			4.0,
			10,
		);

		$this->setRecords($forecast, [$record]);

		$currencyConversionFacade = $this->createMock(CurrencyConversionFacade::class);
		$currencyConversionFacade->method('convertSimpleValue')->willThrowException(new MissingCurrencyPairException());

		$provider = new StockAssetDividendForecastCashflowProvider($currencyConversionFacade);
		$months = $provider->getMonths($forecast);

		self::assertSame(0.0, $months[2]->getNetAmountInCzk());
		self::assertSame(1, $months[2]->getEstimatedItemsCount());
	}

	private function createStockAsset(string $name, string $ticker, CurrencyEnum $currency): StockAsset
	{
		return new StockAsset(
			$name,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$ticker,
			StockAssetExchange::NASDAQ,
			$currency,
			new ImmutableDateTime('2026-01-01'),
			isin: null,
			stockAssetDividendSource: null,
			dividendTax: null,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

	/**
	 * @param array<int> $dividendUsuallyPaidAtMonths
	 * @param array<int> $receivedDividendMonths
	 */
	private function createRecord(
		StockAssetDividendForecast $forecast,
		StockAsset $stockAsset,
		CurrencyEnum $currency,
		array $dividendUsuallyPaidAtMonths,
		array $receivedDividendMonths,
		float $alreadyReceivedDividendPerStock,
		float $expectedDividendPerStock,
		int $pieces,
	): StockAssetDividendForecastRecord
	{
		return new StockAssetDividendForecastRecord(
			$forecast,
			$stockAsset,
			$currency,
			$dividendUsuallyPaidAtMonths,
			$receivedDividendMonths,
			$alreadyReceivedDividendPerStock,
			$alreadyReceivedDividendPerStock * 1.5,
			$pieces,
			0.0,
			0.0,
			0.0,
			0.0,
			$expectedDividendPerStock,
			$expectedDividendPerStock * 1.5,
			null,
			null,
			null,
			null,
			new ImmutableDateTime('2026-01-01'),
		);
	}

	/** @param array<StockAssetDividendForecastRecord> $records */
	private function setRecords(StockAssetDividendForecast $forecast, array $records): void
	{
		$recordsReflection = new ReflectionProperty($forecast, 'records');
		$recordsReflection->setValue($forecast, new ArrayCollection($records));
	}

}
