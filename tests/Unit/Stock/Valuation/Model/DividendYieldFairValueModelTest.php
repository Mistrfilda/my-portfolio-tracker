<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\DividendYieldFairValueModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class DividendYieldFairValueModelTest extends UpdatedTestCase
{

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(80.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Dividenda $4 ročně, cílový yield 3.5% → fair price = 4 / 0.035 = 114.29
		$forwardDividendMock = Mockery::mock(StockValuationData::class);
		$forwardDividendMock->shouldReceive('getFloatValue')->andReturn(4.0);

		$currentYieldMock = Mockery::mock(StockValuationData::class);
		$currentYieldMock->shouldReceive('getFloatValue')->andReturn(5.0); // 5% yield

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($forwardDividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD)
			->andReturn($currentYieldMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEqualsWithDelta(114.29, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(42.86, $response->getCalculatedPercentage(), 0.01);
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Dividend Yield Fair Value', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(150.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Dividenda $4 ročně → fair price = 114.29, ale aktuální cena je 150
		$forwardDividendMock = Mockery::mock(StockValuationData::class);
		$forwardDividendMock->shouldReceive('getFloatValue')->andReturn(4.0);

		$currentYieldMock = Mockery::mock(StockValuationData::class);
		$currentYieldMock->shouldReceive('getFloatValue')->andReturn(2.67);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($forwardDividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD)
			->andReturn($currentYieldMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta(114.29, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(-23.81, $response->getCalculatedPercentage(), 0.01);
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseFairValue(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(114.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		$forwardDividendMock = Mockery::mock(StockValuationData::class);
		$forwardDividendMock->shouldReceive('getFloatValue')->andReturn(4.0);

		$currentYieldMock = Mockery::mock(StockValuationData::class);
		$currentYieldMock->shouldReceive('getFloatValue')->andReturn(3.51);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($forwardDividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD)
			->andReturn($currentYieldMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta(114.29, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(0.25, $response->getCalculatedPercentage(), 0.1);
		$this->assertEquals(StockValuationModelState::FAIR_VALUE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUsesTrailingDividendAsFallback(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Forward není k dispozici, použije se trailing
		$trailingDividendMock = Mockery::mock(StockValuationData::class);
		$trailingDividendMock->shouldReceive('getFloatValue')->andReturn(3.5);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn($trailingDividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		// 3.5 / 0.035 = 100
		$this->assertEqualsWithDelta(100.0, $response->getCalculatedValue(), 0.01);
		$this->assertNotEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoDividend(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(false);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateZeroDividend(): void
	{
		$model = new DividendYieldFairValueModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		$dividendMock = Mockery::mock(StockValuationData::class);
		$dividendMock->shouldReceive('getFloatValue')->andReturn(0.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($dividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
