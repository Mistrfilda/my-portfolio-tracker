<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\FreeCashFlowValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class FreeCashFlowValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new FreeCashFlowValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(80.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$fcfMock = Mockery::mock(StockValuationData::class);
		$fcfMock->shouldReceive('getFloatValue')
			->andReturn(1000000000.0); // 1B FCF

		$sharesMock = Mockery::mock(StockValuationData::class);
		$sharesMock->shouldReceive('getFloatValue')
			->andReturn(100000000.0); // 100M shares

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW)
			->andReturn($fcfMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn($sharesMock);

		$response = $model->calculateResponse($stockValuationMock);

		// FCF per share = 1B / 100M = 10
		// Fair Price = 10 * 1.03 / 0.07 = 147.14
		$this->assertNotNull($response->getAssetPrice());
		$this->assertEqualsWithDelta(147.14, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(83.93, $response->getCalculatedPercentage(), 0.01);
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Free Cash Flow to Equity (FCFE)', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new FreeCashFlowValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$fcfMock = Mockery::mock(StockValuationData::class);
		$fcfMock->shouldReceive('getFloatValue')
			->andReturn(1000000000.0);

		$sharesMock = Mockery::mock(StockValuationData::class);
		$sharesMock->shouldReceive('getFloatValue')
			->andReturn(100000000.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW)
			->andReturn($fcfMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn($sharesMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta(147.14, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(-26.43, $response->getCalculatedPercentage(), 0.01);
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoFCF(): void
	{
		$model = new FreeCashFlowValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoShares(): void
	{
		$model = new FreeCashFlowValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(200.0);

		$fcfMock = Mockery::mock(StockValuationData::class);
		$fcfMock->shouldReceive('getFloatValue')
			->andReturn(1000000000.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW)
			->andReturn($fcfMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::SHARES_OUTSTANDING)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
