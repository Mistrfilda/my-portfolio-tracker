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
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
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
		$stockAsset = $this->createStockAsset('Microsoft', 'MSFT', CurrencyEnum::CZK, dividendTax: 50.0);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now);
		$confirmedDividendMarch = $this->createDividend($stockAsset, 2026, 3, 20.0);
		$confirmedDividendJune = $this->createDividend($stockAsset, 2026, 6, 20.0);
		$this->setDividendRecords($confirmedDividendMarch, [
			new StockAssetDividendRecord($confirmedDividendMarch, 4, 20.0, CurrencyEnum::CZK, null, null, $now),
		]);
		$this->setDividendRecords($confirmedDividendJune, [
			new StockAssetDividendRecord($confirmedDividendJune, 4, 20.0, CurrencyEnum::CZK, null, null, $now),
		]);
		$this->setDividends($stockAsset, [$confirmedDividendMarch, $confirmedDividendJune]);
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

		$provider = $this->createProvider($this->createMock(CurrencyConversionFacade::class));
		$months = $provider->getMonths($forecast);

		self::assertCount(12, $months);
		self::assertSame(10.0, $months[2]->getNetAmountInCzk());
		self::assertSame(20.0, $months[2]->getGrossAmountInCzk());
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

		$provider = $this->createProvider($currencyConversionFacade);
		$months = $provider->getMonths($forecast);

		self::assertSame(0.0, $months[2]->getNetAmountInCzk());
		self::assertSame(1, $months[2]->getEstimatedItemsCount());
	}

	public function testExcludesAlreadyPaidDividendWithoutActualRecordFromCashflow(): void
	{
		$now = new ImmutableDateTime('2026-04-01');
		$stockAsset = $this->createStockAsset('Microsoft', 'MSFT', CurrencyEnum::CZK);
		$missedDividend = $this->createDividend($stockAsset, 2026, 1, 1.0);
		$this->setDividends($stockAsset, [$missedDividend]);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now);
		$record = $this->createRecord(
			$forecast,
			$stockAsset,
			CurrencyEnum::CZK,
			[1, 4, 7, 10],
			[],
			0.0,
			3.0,
			10,
		);

		$this->setRecords($forecast, [$record]);

		$provider = $this->createProvider($this->createMock(CurrencyConversionFacade::class));
		$months = $provider->getMonths($forecast);

		self::assertFalse($months[0]->hasItems());
		self::assertSame(0, $months[0]->getConfirmedItemsCount());
		self::assertSame(0, $months[0]->getEstimatedItemsCount());
		self::assertSame(10.0, $months[3]->getNetAmountInCzk());
		self::assertSame(1, $months[3]->getEstimatedItemsCount());
	}

	public function testExcludesFuturePlannedDividendWithoutActualRecordFromCashflow(): void
	{
		$now = new ImmutableDateTime('2026-06-01');
		$stockAsset = $this->createStockAsset('Kofola', 'KOFOLA.PR', CurrencyEnum::CZK);
		$plannedDividend = $this->createDividend($stockAsset, 2026, 7, 21.0);
		$this->setDividends($stockAsset, [$plannedDividend]);
		$forecast = new StockAssetDividendForecast(2026, StockAssetDividendTrendEnum::NEUTRAL, $now);
		$record = $this->createRecord(
			$forecast,
			$stockAsset,
			CurrencyEnum::CZK,
			[7],
			[],
			0.0,
			21.0,
			10,
		);

		$this->setRecords($forecast, [$record]);

		$provider = $this->createProvider($this->createMock(CurrencyConversionFacade::class));
		$months = $provider->getMonths($forecast);

		self::assertFalse($months[6]->hasItems());
		self::assertSame(0, $months[6]->getConfirmedItemsCount());
		self::assertSame(0, $months[6]->getEstimatedItemsCount());
	}

	private function createProvider(
		CurrencyConversionFacade $currencyConversionFacade,
	): StockAssetDividendForecastCashflowProvider
	{
		return new StockAssetDividendForecastCashflowProvider($currencyConversionFacade);
	}

	private function createStockAsset(
		string $name,
		string $ticker,
		CurrencyEnum $currency,
		float|null $dividendTax = null,
	): StockAsset
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
			dividendTax: $dividendTax,
			brokerDividendCurrency: null,
			shouldDownloadPrice: true,
			shouldDownloadValuation: false,
			watchlist: false,
			industry: null,
		);
	}

	private function createDividend(StockAsset $stockAsset, int $year, int $month, float $amount): StockAssetDividend
	{
		$date = new ImmutableDateTime(sprintf('%d-%02d-01', $year, $month));

		return new StockAssetDividend(
			$stockAsset,
			$date,
			$date,
			null,
			CurrencyEnum::CZK,
			$amount,
			$date,
			StockAssetDividendTypeEnum::REGULAR,
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

	/** @param array<StockAssetDividend> $dividends */
	private function setDividends(StockAsset $stockAsset, array $dividends): void
	{
		$dividendsReflection = new ReflectionProperty($stockAsset, 'dividends');
		$dividendsReflection->setValue($stockAsset, new ArrayCollection($dividends));
	}

	/** @param array<StockAssetDividendRecord> $records */
	private function setDividendRecords(StockAssetDividend $dividend, array $records): void
	{
		$recordsReflection = new ReflectionProperty($dividend, 'records');
		$recordsReflection->setValue($dividend, new ArrayCollection($records));
	}

}
