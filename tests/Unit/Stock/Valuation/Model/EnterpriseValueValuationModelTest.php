<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\EnterpriseValueValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class EnterpriseValueValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new EnterpriseValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$ebitdaDataMock = Mockery::mock(StockValuationData::class);
		$ebitdaDataMock->shouldReceive('getFloatValue')
			->andReturn(1_000_000_000.0); // 1B EBITDA

		$sharesDataMock = Mockery::mock(StockValuationData::class);
		$sharesDataMock->shouldReceive('getFloatValue')
			->andReturn(100_000_000.0); // 100M shares

		$debtDataMock = Mockery::mock(StockValuationData::class);
		$debtDataMock->shouldReceive('getFloatValue')
			->andReturn(2_000_000_000.0); // 2B debt

		$cashDataMock = Mockery::mock(StockValuationData::class);
		$cashDataMock->shouldReceive('getFloatValue')
			->andReturn(500_000_000.0); // 500M cash

		$evEbitdaDataMock = Mockery::mock(StockValuationData::class);
		$evEbitdaDataMock->shouldReceive('getFloatValue')
			->andReturn(8.0);

		// Fair EV = 1B * 10 = 10B
		// Net Debt = 2B - 0.5B = 1.5B
		// Fair Equity = 10B - 1.5B = 8.5B
		// Fair Price = 8.5B / 100M = 85
		$expectedFairPrice = 85.0;

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EBITDA)
			->andReturn($ebitdaDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn($sharesDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT)
			->andReturn($debtDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_CASH)
			->andReturn($cashDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EV_EBITDA)
			->andReturn($evEbitdaDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals($expectedFairPrice, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('EV/EBITDA Model', $response->getLabel());
	}

	public function testCalculateResponseUnableToCalculateNegativeEquityValue(): void
	{
		$model = new EnterpriseValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$ebitdaDataMock = Mockery::mock(StockValuationData::class);
		$ebitdaDataMock->shouldReceive('getFloatValue')
			->andReturn(100_000_000.0); // 100M EBITDA

		$sharesDataMock = Mockery::mock(StockValuationData::class);
		$sharesDataMock->shouldReceive('getFloatValue')
			->andReturn(100_000_000.0);

		$debtDataMock = Mockery::mock(StockValuationData::class);
		$debtDataMock->shouldReceive('getFloatValue')
			->andReturn(5_000_000_000.0); // 5B debt - massive!

		$cashDataMock = Mockery::mock(StockValuationData::class);
		$cashDataMock->shouldReceive('getFloatValue')
			->andReturn(100_000_000.0); // 100M cash

		// Fair EV = 100M * 10 = 1B
		// Net Debt = 5B - 0.1B = 4.9B
		// Fair Equity = 1B - 4.9B = -3.9B (negative!)

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EBITDA)
			->andReturn($ebitdaDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn($sharesDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT)
			->andReturn($debtDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_CASH)
			->andReturn($cashDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EV_EBITDA)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoEbitda(): void
	{
		$model = new EnterpriseValueValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EBITDA)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_DEBT)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TOTAL_CASH)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::EV_EBITDA)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
