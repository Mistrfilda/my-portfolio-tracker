<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Dividend\Record;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordService;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\StockPosition;
use App\Test\UpdatedTestCase;
use Doctrine\Common\Collections\ArrayCollection;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class StockAssetDividendRecordServiceTest extends UpdatedTestCase
{

	private StockAssetDividendRecordService $service;

	private DatetimeFactory $datetimeFactory;

	private CurrencyConversionFacade $currencyConversionFacade;

	protected function setUp(): void
	{
		parent::setUp();

		$this->datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$this->currencyConversionFacade = Mockery::mock(CurrencyConversionFacade::class);

		$this->service = new StockAssetDividendRecordService(
			$this->datetimeFactory,
			$this->currencyConversionFacade,
		);
	}

	public function testProcessDividendRecordsWithEmptyCollections(): void
	{
		$dividends = new ArrayCollection();
		$positions = new ArrayCollection();

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(0, $result);
	}

	public function testProcessDividendRecordsWithNoPositions(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getBrokerDividendCurrency')->andReturn(null);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(2.50);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection();

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(0, $result);
	}

	public function testProcessDividendRecordsWithPositionBeforeExDate(): void
	{
		$now = new ImmutableDateTime();
		$this->datetimeFactory->shouldReceive('createNow')->andReturn($now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getBrokerDividendCurrency')->andReturn(null);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(2.50);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$position = Mockery::mock(StockPosition::class);
		$position->shouldReceive('getStockClosedPosition')->andReturn(null);
		$position->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-01-01'));
		$position->shouldReceive('getOrderPiecesCount')->andReturn(10);

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection([$position]);

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(1, $result);
		$this->assertInstanceOf(StockAssetDividendRecord::class, $result->first());
	}

	public function testProcessDividendRecordsWithPositionAfterExDate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getBrokerDividendCurrency')->andReturn(null);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(2.50);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$position = Mockery::mock(StockPosition::class);
		$position->shouldReceive('getStockClosedPosition')->andReturn(null);
		$position->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-02-01'));

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection([$position]);

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(0, $result);
	}

	public function testProcessDividendRecordsWithClosedPositionBeforeExDate(): void
	{
		$stockAsset = Mockery::mock(StockAsset::class);

		$closedPosition = Mockery::mock(StockClosedPosition::class);
		$closedPosition->shouldReceive('getDate')->andReturn(new ImmutableDateTime('2024-01-10'));

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(2.50);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$position = Mockery::mock(StockPosition::class);
		$position->shouldReceive('getStockClosedPosition')->andReturn($closedPosition);
		$position->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-01-01'));

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection([$position]);

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(0, $result);
	}

	public function testProcessDividendRecordsWithBrokerCurrency(): void
	{
		$now = new ImmutableDateTime();
		$this->datetimeFactory->shouldReceive('createNow')->andReturn($now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getBrokerDividendCurrency')->andReturn(CurrencyEnum::CZK);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(2.50);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$position = Mockery::mock(StockPosition::class);
		$position->shouldReceive('getStockClosedPosition')->andReturn(null);
		$position->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-01-01'));
		$position->shouldReceive('getOrderPiecesCount')->andReturn(10);

		$convertedSummaryPrice = new SummaryPrice(CurrencyEnum::CZK, 575.0, 1);
		$this->currencyConversionFacade->shouldReceive('getConvertedSummaryPrice')
			->once()
			->andReturn($convertedSummaryPrice);

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection([$position]);

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(1, $result);
	}

	public function testProcessDividendRecordsWithMultiplePositions(): void
	{
		$now = new ImmutableDateTime();
		$this->datetimeFactory->shouldReceive('createNow')->andReturn($now);

		$stockAsset = Mockery::mock(StockAsset::class);
		$stockAsset->shouldReceive('getBrokerDividendCurrency')->andReturn(null);

		$dividend = Mockery::mock(StockAssetDividend::class);
		$dividend->shouldReceive('getCurrency')->andReturn(CurrencyEnum::USD);
		$dividend->shouldReceive('getExDate')->andReturn(new ImmutableDateTime('2024-01-15'));
		$dividend->shouldReceive('getAmount')->andReturn(1.00);
		$dividend->shouldReceive('getStockAsset')->andReturn($stockAsset);

		$position1 = Mockery::mock(StockPosition::class);
		$position1->shouldReceive('getStockClosedPosition')->andReturn(null);
		$position1->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-01-01'));
		$position1->shouldReceive('getOrderPiecesCount')->andReturn(5);

		$position2 = Mockery::mock(StockPosition::class);
		$position2->shouldReceive('getStockClosedPosition')->andReturn(null);
		$position2->shouldReceive('getOrderDate')->andReturn(new ImmutableDateTime('2024-01-05'));
		$position2->shouldReceive('getOrderPiecesCount')->andReturn(10);

		$dividends = new ArrayCollection([$dividend]);
		$positions = new ArrayCollection([$position1, $position2]);

		$result = $this->service->processDividendRecords($dividends, $positions);

		$this->assertCount(1, $result);
		$record = $result->first();
		$this->assertInstanceOf(StockAssetDividendRecord::class, $record);
		$this->assertSame(15, $record->getTotalPiecesHeldAtExDate());
		$this->assertSame(15.0, $record->getTotalAmount());
	}

}
