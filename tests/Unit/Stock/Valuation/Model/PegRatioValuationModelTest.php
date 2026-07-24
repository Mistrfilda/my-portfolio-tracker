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
		$response = $model->calculateResponse($this->createStockValuation(100.0, 0.8));

		$this->assertNotNull($response->getAssetPrice());
		$this->assertEquals(125.0, $response->getCalculatedValue());
		$this->assertEquals(25.0, $response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::UNDERPRICED, $response->getStockValuationModelTrend());
		$this->assertEquals('PEG Ratio Model', $response->getLabel());
		$this->assertSame(
			[StockValuationTypeEnum::CURRENT_PRICE, StockValuationTypeEnum::PEG_RATIO],
			$response->getUsedStockValuationDataTypes(),
		);
	}

	public function testCalculateResponseOverpriced(): void
	{
		$model = new PegRatioValuationModel();
		$response = $model->calculateResponse($this->createStockValuation(100.0, 2.0));

		$this->assertEquals(50.0, $response->getCalculatedValue());
		$this->assertEquals(-50.0, $response->getCalculatedPercentage());
		$this->assertEquals(StockValuationModelState::OVERPRICED, $response->getStockValuationModelTrend());
	}

	public function testUsesCurrentPegRatioInsteadOfQuarterlyGrowthForAgcoLikeData(): void
	{
		$model = new PegRatioValuationModel();
		$response = $model->calculateResponse($this->createStockValuation(117.19, 1.14));

		$this->assertEqualsWithDelta(102.80, $response->getCalculatedValue() ?? 0.0, 0.01);
		$this->assertEqualsWithDelta(-12.28, $response->getCalculatedPercentage() ?? 0.0, 0.01);
		$this->assertEquals(StockValuationModelState::NEUTRAL, $response->getStockValuationModelTrend());
	}

	public function testCalculateResponseUnableToCalculateWithoutPositivePeg(): void
	{
		$model = new PegRatioValuationModel();

		$response = $model->calculateResponse($this->createStockValuation(100.0, null));
		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());

		$response = $model->calculateResponse($this->createStockValuation(100.0, 0.0));
		$this->assertEquals(StockValuationModelState::UNABLE_TO_CALCULATE, $response->getStockValuationModelTrend());
	}

	private function createStockValuation(float $currentPrice, float|null $pegRatio): StockValuation
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);
		$stockAssetMock->shouldReceive('getAssetCurrentPrice->getPrice')
			->andReturn($currentPrice);
		$stockAssetMock->shouldReceive('getCurrency')
			->zeroOrMoreTimes()
			->andReturn(CurrencyEnum::USD);

		$pegDataMock = null;
		if ($pegRatio !== null) {
			$pegDataMock = Mockery::mock(StockValuationData::class);
			$pegDataMock->shouldReceive('getFloatValue')
				->andReturn($pegRatio);
		}

		$stockValuationMock = Mockery::mock(StockValuation::class);
		$stockValuationMock->shouldReceive('getStockAsset')
			->andReturn($stockAssetMock);
		$stockValuationMock->shouldReceive('getValuationDataByType')
			->with(StockValuationTypeEnum::PEG_RATIO)
			->andReturn($pegDataMock);

		return $stockValuationMock;
	}

}
