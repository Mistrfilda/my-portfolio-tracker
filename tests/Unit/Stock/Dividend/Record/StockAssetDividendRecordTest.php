<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Record;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\StockAssetDividend;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class StockAssetDividendRecordTest extends UpdatedTestCase
{

	public function testConstructor(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('Apple Inc.');
		$stockAsset->shouldReceive('getTicker')->andReturn('AAPL');
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			250.0,
			CurrencyEnum::USD,
			5750.0,
			CurrencyEnum::CZK,
			$now,
		);

		$this->assertSame($dividend, $record->getStockAssetDividend());
		$this->assertSame(100, $record->getTotalPiecesHeldAtExDate());
		$this->assertSame(250.0, $record->getTotalAmount());
		$this->assertSame(CurrencyEnum::USD, $record->getCurrency());
		$this->assertSame(5750.0, $record->getTotalAmountInBrokerCurrency());
		$this->assertSame(CurrencyEnum::CZK, $record->getBrokerCurrency());
		$this->assertTrue($record->isReinvested());
	}

	public function testUpdate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime('2024-01-01');

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			250.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
		);

		$updateTime = new ImmutableDateTime('2024-02-01');
		$record->update(
			150,
			375.0,
			CurrencyEnum::EUR,
			9000.0,
			CurrencyEnum::CZK,
			$updateTime,
		);

		$this->assertSame(150, $record->getTotalPiecesHeldAtExDate());
		$this->assertSame(375.0, $record->getTotalAmount());
		$this->assertSame(CurrencyEnum::EUR, $record->getCurrency());
		$this->assertSame(9000.0, $record->getTotalAmountInBrokerCurrency());
		$this->assertSame(CurrencyEnum::CZK, $record->getBrokerCurrency());
		$this->assertSame($updateTime, $record->getUpdatedAt());
	}

	public function testGetStockAssetChartLabel(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getName')->andReturn('Apple Inc.');
		$stockAsset->shouldReceive('getTicker')->andReturn('AAPL');

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			250.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
		);

		$this->assertSame('Apple Inc. (AAPL)', $record->getStockAssetChartLabel());
	}

	public function testGetSummaryPriceWithTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			100.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
		);

		$summaryPrice = $record->getSummaryPrice(true);

		$this->assertSame(CurrencyEnum::USD, $summaryPrice->getCurrency());
		$this->assertSame(85.0, $summaryPrice->getPrice());
	}

	public function testGetSummaryPriceWithoutTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			100.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
		);

		$summaryPrice = $record->getSummaryPrice(false);

		$this->assertSame(100.0, $summaryPrice->getPrice());
	}

	public function testGetSummaryPriceInBrokerCurrencyWithTax(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(15.0);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			100.0,
			CurrencyEnum::USD,
			2300.0,
			CurrencyEnum::CZK,
			$now,
		);

		$summaryPrice = $record->getSummaryPriceInBrokerCurrency(true);

		$this->assertSame(CurrencyEnum::CZK, $summaryPrice->getCurrency());
		$this->assertSame(1955.0, $summaryPrice->getPrice());
	}

	public function testGetSummaryPriceInBrokerCurrencyFallsBackWhenNoBrokerCurrency(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getDividendTax')->andReturn(null);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$now = new ImmutableDateTime();

		$record = new StockAssetDividendRecord(
			$dividend,
			100,
			100.0,
			CurrencyEnum::USD,
			null,
			null,
			$now,
		);

		$summaryPrice = $record->getSummaryPriceInBrokerCurrency(true);

		$this->assertSame(CurrencyEnum::USD, $summaryPrice->getCurrency());
		$this->assertSame(100.0, $summaryPrice->getPrice());
	}

}
