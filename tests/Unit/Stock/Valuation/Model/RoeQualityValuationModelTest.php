<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\RoeQualityValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class RoeQualityValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseExcellentRoe(): void
	{
		$model = new RoeQualityValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(50.0);

		$roeDataMock = Mockery::mock(StockValuationData::class);
		$roeDataMock->shouldReceive('getFloatValue')
			->andReturn(25.0); // Excellent ROE (> 20%)

		// ROE 25% = P/B multiplier 2.5 + (5/10)*0.5 = 2.75
		// Fair Price = 50 * 2.75 = 137.5
		$expectedFairPrice = 50.0 * 2.75;

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::RETURN_ON_EQUITY)
			->andReturn($roeDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEqualsWithDelta($expectedFairPrice, $response->getCalculatedValue(), 0.01);
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('ROE Quality Model', $response->getLabel());
	}

	public function testCalculateResponsePoorRoe(): void
	{
		$model = new RoeQualityValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(80.0);

		$roeDataMock = Mockery::mock(StockValuationData::class);
		$roeDataMock->shouldReceive('getFloatValue')
			->andReturn(3.0); // Poor ROE (0-5%)

		// ROE 3% = P/B multiplier 0.8 + (3/5)*0.2 = 0.92
		// Fair Price = 80 * 0.92 = 73.6
		$expectedFairPrice = 80.0 * (0.8 + (3.0 / 5.0) * 0.2);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::RETURN_ON_EQUITY)
			->andReturn($roeDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta($expectedFairPrice, $response->getCalculatedValue(), 0.01);
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseNegativeRoe(): void
	{
		$model = new RoeQualityValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(100.0);

		$roeDataMock = Mockery::mock(StockValuationData::class);
		$roeDataMock->shouldReceive('getFloatValue')
			->andReturn(-5.0); // Negative ROE

		// ROE -5% = P/B multiplier 0.8 - (5/10)*0.3 = 0.65
		// Fair Price = 100 * 0.65 = 65
		$expectedFairPrice = 100.0 * (0.8 - (5.0 / 10.0) * 0.3);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::RETURN_ON_EQUITY)
			->andReturn($roeDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta($expectedFairPrice, $response->getCalculatedValue(), 0.01);
	}

	public function testCalculateResponseUnableToCalculateNoRoe(): void
	{
		$model = new RoeQualityValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(100.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::RETURN_ON_EQUITY)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
