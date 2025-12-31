<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Model;

use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Model\Price\DividendPayoutSafetyModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class DividendPayoutSafetyModelTest extends UpdatedTestCase
{

	public function testCalculateResponseIdealPayoutRatio(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Payout ratio 50% = ideální, EPS = $5
		// Safety multiplier ~1.15, Base P/E = 15
		// Fair price = 5 * (15 * 1.15) = 86.25
		$payoutMock = Mockery::mock(StockValuationData::class);
		$payoutMock->shouldReceive('getFloatValue')->andReturn(50.0);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(5.0);

		$dividendMock = Mockery::mock(StockValuationData::class);
		$dividendMock->shouldReceive('getFloatValue')->andReturn(2.5);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn($payoutMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($dividendMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		// EPS 5 * P/E 15 * multiplier 1.15 = 86.25
		$this->assertEqualsWithDelta(86.25, $response->getCalculatedValue(), 0.1);
		$this->assertEquals('Dividend Payout Safety', $response->getLabel());
	}

	public function testCalculateResponseLowPayoutRatioUnderpriced(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(50.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Payout ratio 35% - zdravé, s prostorem pro růst
		$payoutMock = Mockery::mock(StockValuationData::class);
		$payoutMock->shouldReceive('getFloatValue')->andReturn(35.0);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(5.0);

		$dividendMock = Mockery::mock(StockValuationData::class);
		$dividendMock->shouldReceive('getFloatValue')->andReturn(1.75);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn($payoutMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($dividendMock);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertNotNull($response->getAssetPrice());
		$this->assertGreaterThan(50.0, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseHighPayoutRatioOverpriced(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Payout ratio 85% - vysoké, riskantní
		$payoutMock = Mockery::mock(StockValuationData::class);
		$payoutMock->shouldReceive('getFloatValue')->andReturn(85.0);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(5.0);

		$dividendMock = Mockery::mock(StockValuationData::class);
		$dividendMock->shouldReceive('getFloatValue')->andReturn(4.25);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn($payoutMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($dividendMock);

		$response = $model->calculateResponse($stockValuationMock);

		// High payout = penalizace, fair value bude nižší
		$this->assertLessThan(100.0, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseCriticalPayoutRatio(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		// Payout ratio 95% - kritické, neudržitelné
		$payoutMock = Mockery::mock(StockValuationData::class);
		$payoutMock->shouldReceive('getFloatValue')->andReturn(95.0);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(5.0);

		$dividendMock = Mockery::mock(StockValuationData::class);
		$dividendMock->shouldReceive('getFloatValue')->andReturn(4.75);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn($payoutMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn($dividendMock);

		$response = $model->calculateResponse($stockValuationMock);

		// Kritické payout = výrazná penalizace
		$this->assertLessThan(75.0, $response->getCalculatedValue());
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNoDividend(): void
	{
		$model = new DividendPayoutSafetyModel();

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

	public function testCalculateResponseUnableToCalculateNoPayoutRatio(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(5.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn(null);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateNegativeEps(): void
	{
		$model = new DividendPayoutSafetyModel();

		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldIgnoreMissing();
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn(100.0);
		$stockAssetMock->shouldReceive('doesPaysDividends')
			->andReturn(true);

		$payoutMock = Mockery::mock(StockValuationData::class);
		$payoutMock->shouldReceive('getFloatValue')->andReturn(50.0);

		$epsMock = Mockery::mock(StockValuationData::class);
		$epsMock->shouldReceive('getFloatValue')->andReturn(-2.0);

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PAYOUT_RATIO)
			->andReturn($payoutMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::DILUTED_EPS)
			->andReturn($epsMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE)
			->andReturn(null);

		$response = $model->calculateResponse($stockValuationMock);

		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

}
