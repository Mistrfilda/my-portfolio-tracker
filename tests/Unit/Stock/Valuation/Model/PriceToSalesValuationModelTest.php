<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\PriceToSalesValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class PriceToSalesValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new PriceToSalesValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(40.0); // Revenue per share = 40, Fair Price = 40 * 2.0 = 80

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::REVENUE_PER_SHARE)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(80.0, $response->getCalculatedValue());
		$this->assertEquals(60.0, $response->getCalculatedPercentage()); // (80 - 50) / 50 * 100
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Price to Sales (P/S)', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new PriceToSalesValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(40.0); // Fair Price = 80

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::REVENUE_PER_SHARE)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(80.0, $response->getCalculatedValue());
		$this->assertEquals(-20.0, $response->getCalculatedPercentage()); // (80 - 100) / 100 * 100
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseFairValue(): void
	{
		$model = new PriceToSalesValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(80.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(40.0); // Fair Price = 80

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::REVENUE_PER_SHARE)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(80.0, $response->getCalculatedValue());
		$this->assertEquals(0.0, $response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::FAIR_VALUE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculate(): void
	{
		$model = new PriceToSalesValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::REVENUE_PER_SHARE)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
