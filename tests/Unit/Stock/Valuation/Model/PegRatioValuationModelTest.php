<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\PegRatioValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class PegRatioValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new PegRatioValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(5.0);

		$growthDataMock = Mockery::mock(StockValuationData::class);
		$growthDataMock->shouldReceive('getFloatValue')
			->andReturn(25.0); // 25% growth

		// Fair P/E = 1.0 * 25 = 25, Fair Price = 5 * 25 = 125
		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH)
			->andReturn($growthDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(125.0, $response->getCalculatedValue());
		$this->assertEquals(25.0, $response->getCalculatedPercentage()); // (125 - 100) / 100 * 100
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('PEG Ratio Model', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new PegRatioValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(5.0);

		$growthDataMock = Mockery::mock(StockValuationData::class);
		$growthDataMock->shouldReceive('getFloatValue')
			->andReturn(10.0); // 10% growth

		// Fair P/E = 1.0 * 10 = 10, Fair Price = 5 * 10 = 50
		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH)
			->andReturn($growthDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(50.0, $response->getCalculatedValue());
		$this->assertEquals(-75.0, $response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNegativeGrowth(): void
	{
		$model = new PegRatioValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(5.0);

		$growthDataMock = Mockery::mock(StockValuationData::class);
		$growthDataMock->shouldReceive('getFloatValue')
			->andReturn(-10.0); // Negative growth

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH)
			->andReturn($growthDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
