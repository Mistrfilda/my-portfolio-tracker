<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\PriceEarningsValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class PriceEarningsValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new PriceEarningsValuationModel();

		// Create mock industry
		$industryMock = Mockery::mock(StockAssetIndustry::class);
		$industryMock->shouldReceive('getCurrentPERatio')
			->andReturn(15.0);

		// Create mock stock asset
		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('getIndustry')
			->andReturn($industryMock);

		// Create mock valuation data for diluted EPS
		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(10.0); // EPS = 10, Industry PE = 15, Fair Price = 150

		// Create mock stock valuation
		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(150.0, $response->getCalculatedValue());
		$this->assertEquals(50.0, $response->getCalculatedPercentage()); // (150 - 100) / 100 * 100
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('P/E Ratio Valuation', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new PriceEarningsValuationModel();

		$industryMock = Mockery::mock(StockAssetIndustry::class);
		$industryMock->shouldReceive('getCurrentPERatio')
			->andReturn(15.0);

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0); // Current price high
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('getIndustry')
			->andReturn($industryMock);

		$valuationDataMock = Mockery::mock(StockValuationData::class);
		$valuationDataMock->shouldReceive('getFloatValue')
			->andReturn(10.0); // Fair Price = 150

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($valuationDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(150.0, $response->getCalculatedValue());
		$this->assertEquals(-25.0, $response->getCalculatedPercentage()); // (150 - 200) / 200 * 100
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculate(): void
	{
		$model = new PriceEarningsValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn(null); // No EPS data
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNull($response->getAssetPrice());
		$this->assertNull($response->getCalculatedValue());
		$this->assertNull($response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
