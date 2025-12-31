<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\GrahamNumberValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class GrahamNumberValuationModelTest extends UpdatedTestCase
{

	public function testCalculateResponseUnderpriced(): void
	{
		$model = new GrahamNumberValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(5.0);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(20.0);

		// Graham Number = sqrt(22.5 * 5 * 20) = sqrt(2250) ≈ 47.43
		$expectedFairPrice = sqrt(22.5 * 5.0 * 20.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEqualsWithDelta($expectedFairPrice, $response->getCalculatedValue(), 0.01);
		$this->assertEquals('Graham Number', $response->getLabel());
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new GrahamNumberValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(3.0);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(15.0);

		// Graham Number = sqrt(22.5 * 3 * 15) = sqrt(1012.5) ≈ 31.82
		$expectedFairPrice = sqrt(22.5 * 3.0 * 15.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEqualsWithDelta($expectedFairPrice, $response->getCalculatedValue(), 0.01);
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoEps(): void
	{
		$model = new GrahamNumberValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn(
				Mockery::mock(StockValuationData::class)->shouldReceive('getFloatValue')->andReturn(20.0)->getMock(),
			);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNegativeEps(): void
	{
		$model = new GrahamNumberValuationModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);

		$epsDataMock = Mockery::mock(StockValuationData::class);
		$epsDataMock->shouldReceive('getFloatValue')
			->andReturn(-5.0);

		$bookValueDataMock = Mockery::mock(StockValuationData::class);
		$bookValueDataMock->shouldReceive('getFloatValue')
			->andReturn(20.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsDataMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::BOOK_VALUE_PER_SHARE)
			->andReturn($bookValueDataMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
