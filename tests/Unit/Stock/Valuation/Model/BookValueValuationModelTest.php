<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class BookValueValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new BookValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->makePartial();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);

		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(80.0); // Book Value = 80, Fair Price = 80 * 1.5 = 120

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(120.0, $response->getCalculatedValue());
		$this->assertEquals(20.0, $response->getCalculatedPercentage()); // (120 - 100) / 100 * 100
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Book Value Valuation (P/B)', $response->getLabel());
	}

	public function testCalculateResponseFairValue(): void
	{
		$model = new BookValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(120.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(80.0); // Fair Price = 120

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(120.0, $response->getCalculatedValue());
		$this->assertEquals(0.0, $response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::FAIR_VALUE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoBookValue(): void
	{
		$model = new BookValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn(null);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(120.0);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
