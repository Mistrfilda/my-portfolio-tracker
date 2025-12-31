<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\DebtAdjustedValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class DebtAdjustedValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseLowDebtHighLiquidity(): void
	{
		$model = new DebtAdjustedValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(100.0);

		$debtEquityDataMock = Mockery::mock(StockValuationData::class);
		$debtEquityDataMock->shouldReceive('getFloatValue')
			->andReturn(0.2); // Very low debt

		$currentRatioDataMock = Mockery::mock(StockValuationData::class);
		$currentRatioDataMock->shouldReceive('getFloatValue')
			->andReturn(2.5); // Excellent liquidity

		// Base = 100 * 1.2 = 120
		// Debt adjustment: low debt (+15%) + excellent liquidity (+10%) = 1.25
		// Fair Price = 120 * 1.25 = 150

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT_EQUITY)
			->andReturn($debtEquityDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::CURRENT_RATIO)
			->andReturn($currentRatioDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(150.0, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Debt-Adjusted Book Value', $response->getLabel());
	}

	public function testCalculateResponseHighDebtLowLiquidity(): void
	{
		$model = new DebtAdjustedValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(100.0);

		$debtEquityDataMock = Mockery::mock(StockValuationData::class);
		$debtEquityDataMock->shouldReceive('getFloatValue')
			->andReturn(4.0); // Extreme debt

		$currentRatioDataMock = Mockery::mock(StockValuationData::class);
		$currentRatioDataMock->shouldReceive('getFloatValue')
			->andReturn(0.8); // Poor liquidity

		// Base = 100 * 1.2 = 120
		// Debt adjustment: extreme debt (-30%) + poor liquidity (-10%) = -40%
		// But capped at 0.7, so Fair Price = 120 * 0.7 = 84

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT_EQUITY)
			->andReturn($debtEquityDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::CURRENT_RATIO)
			->andReturn($currentRatioDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(84.0, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoBookValue(): void
	{
		$model = new DebtAdjustedValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT_EQUITY)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::CURRENT_RATIO)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
