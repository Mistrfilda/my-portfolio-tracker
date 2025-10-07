<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\DividendDiscountValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class DividendDiscountValuationModelTest extends UpdatedTestCase
{

	protected function tearDown(): void
	{
		parent::tearDown();
	}

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new DividendDiscountValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$forwardDividendMock = Mockery::mock(StockValuationData::class);
		$forwardDividendMock->shouldReceive('getFloatValue')
			->andReturn(5.0); // Forward dividend = 5

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($forwardDividendMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		// Expected: D * (1 + g) / (r - g) = 5 * 1.03 / 0.07 = 73.57
		$this->assertNotNull($response->getAssetPrice());
		$this->assertEqualsWithDelta(73.57, $response->getCalculatedValue(), 0.01);
		$this->assertEqualsWithDelta(47.14, $response->getCalculatedPercentage(), 0.01);
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('Dividend Discount Model (DDM)', $response->getLabel());
	}

	public function testCalculateResponseWithTrailingDividend(): void
	{
		$model = new DividendDiscountValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$trailingDividendMock = Mockery::mock(StockValuationData::class);
		$trailingDividendMock->shouldReceive('getFloatValue')
			->andReturn(4.5);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE)
			->andReturn($trailingDividendMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoDividends(): void
	{
		$model = new DividendDiscountValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(false);
		$stockAssetMock->shouldIgnoreMissing();

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
