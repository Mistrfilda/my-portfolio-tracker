<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Comparison\Industry;

use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparison;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparisonMetric;
use App\Stock\Valuation\Comparison\Industry\StockIndustryComparisonState;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Test\UpdatedTestCase;
use Mockery;

class StockIndustryComparisonTest extends UpdatedTestCase
{

	public function testGetMetrics(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$metric1 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			15.0,
			20.0,
			StockIndustryComparisonState::BELOW_AVERAGE,
		);
		$metric2 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			12.0,
			10.0,
			StockIndustryComparisonState::ABOVE_AVERAGE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metric1, $metric2]);

		$this->assertCount(2, $comparison->getMetrics());
		$this->assertSame($stockAssetMock, $comparison->getStockAsset());
	}

	public function testGetPositiveMetrics(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E je nižší hodnota lepší, takže BELOW_AVERAGE je pozitivní
		$positiveMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			15.0,
			20.0,
			StockIndustryComparisonState::BELOW_AVERAGE,
		);

		// Pro P/E je vyšší hodnota negativní
		$negativeMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			25.0,
			20.0,
			StockIndustryComparisonState::ABOVE_AVERAGE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$positiveMetric, $negativeMetric]);
		$positiveMetrics = $comparison->getPositiveMetrics();

		$this->assertCount(1, $positiveMetrics);
		$this->assertSame(StockValuationTypeEnum::TRAILING_PE, array_values($positiveMetrics)[0]->getMetric());
	}

	public function testGetNegativeMetrics(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E je vyšší hodnota negativní
		$negativeMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			25.0,
			20.0,
			StockIndustryComparisonState::ABOVE_AVERAGE,
		);

		// IN_LINE není negativní
		$neutralMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			20.0,
			20.0,
			StockIndustryComparisonState::IN_LINE,
		);

		// NO_DATA není negativní
		$noDataMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::PEG_RATIO,
			1.5,
			null,
			StockIndustryComparisonState::NO_DATA,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$negativeMetric, $neutralMetric, $noDataMetric]);
		$negativeMetrics = $comparison->getNegativeMetrics();

		$this->assertCount(1, $negativeMetrics);
		$this->assertSame(StockValuationTypeEnum::TRAILING_PE, array_values($negativeMetrics)[0]->getMetric());
	}

	public function testHasIndustryDataReturnsTrue(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$metricWithData = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			15.0,
			20.0,
			StockIndustryComparisonState::BELOW_AVERAGE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metricWithData]);

		$this->assertTrue($comparison->hasIndustryData());
	}

	public function testHasIndustryDataReturnsFalse(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$metricWithoutData = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			15.0,
			null,
			StockIndustryComparisonState::NO_DATA,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metricWithoutData]);

		$this->assertFalse($comparison->hasIndustryData());
	}

	public function testHasIndustryDataReturnsFalseForEmptyMetrics(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$comparison = new StockIndustryComparison($stockAssetMock, []);

		$this->assertFalse($comparison->hasIndustryData());
	}

	public function testGetOverallScoreReturnsNullWithoutIndustryData(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$metricWithoutData = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			15.0,
			null,
			StockIndustryComparisonState::NO_DATA,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metricWithoutData]);

		$this->assertNull($comparison->getOverallScore());
	}

	public function testGetOverallScoreMaxScoreForAllPositiveSignificant(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E metriky je nižší hodnota lepší - SIGNIFICANTLY_BELOW dává 100 bodů
		$metric1 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			10.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_BELOW,
		);

		$metric2 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			8.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_BELOW,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metric1, $metric2]);

		// Obě metriky jsou pozitivní (significantly below pro P/E) = 100 + 100 = 200 / 200 = 100%
		$this->assertEquals(100.0, $comparison->getOverallScore());
	}

	public function testGetOverallScoreZeroForAllNegativeSignificant(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E metriky je vyšší hodnota špatná - SIGNIFICANTLY_ABOVE dává 0 bodů
		$metric1 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			30.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_ABOVE,
		);

		$metric2 = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			28.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_ABOVE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metric1, $metric2]);

		// Obě metriky jsou negativní = 0 + 0 = 0 / 200 = 0%
		$this->assertEquals(0.0, $comparison->getOverallScore());
	}

	public function testGetOverallScoreMixedMetrics(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pozitivní metrika - 100 bodů
		$positiveMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			10.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_BELOW,
		);

		// IN_LINE - 50 bodů
		$neutralMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			20.0,
			20.0,
			StockIndustryComparisonState::IN_LINE,
		);

		// Negativní metrika - 0 bodů
		$negativeMetric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::PEG_RATIO,
			3.0,
			1.5,
			StockIndustryComparisonState::SIGNIFICANTLY_ABOVE,
		);

		$comparison = new StockIndustryComparison(
			$stockAssetMock,
			[$positiveMetric, $neutralMetric, $negativeMetric],
		);

		// (100 + 50 + 0) / 300 * 100 = 50%
		$this->assertEquals(50.0, $comparison->getOverallScore());
	}

	public function testGetOverallScoreSkipsMetricsWithoutIndustryData(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		$metricWithData = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			10.0,
			20.0,
			StockIndustryComparisonState::SIGNIFICANTLY_BELOW,
		);

		$metricWithoutData = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::FORWARD_PE,
			15.0,
			null,
			StockIndustryComparisonState::NO_DATA,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metricWithData, $metricWithoutData]);

		// Pouze metrika s daty: 100 / 100 * 100 = 100%
		$this->assertEquals(100.0, $comparison->getOverallScore());
	}

	public function testGetOverallScoreAboveAverageMetric(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E: ABOVE_AVERAGE je negativní (vyšší P/E je špatné) = 25 bodů
		$metric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			22.0,
			20.0,
			StockIndustryComparisonState::ABOVE_AVERAGE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metric]);

		// 25 / 100 * 100 = 25%
		$this->assertEquals(25.0, $comparison->getOverallScore());
	}

	public function testGetOverallScoreBelowAverageMetric(): void
	{
		$stockAssetMock = Mockery::mock(StockAsset::class);

		// Pro P/E: BELOW_AVERAGE je pozitivní (nižší P/E je dobré) = 75 bodů
		$metric = new StockIndustryComparisonMetric(
			StockValuationTypeEnum::TRAILING_PE,
			18.0,
			20.0,
			StockIndustryComparisonState::BELOW_AVERAGE,
		);

		$comparison = new StockIndustryComparison($stockAssetMock, [$metric]);

		// 75 / 100 * 100 = 75%
		$this->assertEquals(75.0, $comparison->getOverallScore());
	}

}
