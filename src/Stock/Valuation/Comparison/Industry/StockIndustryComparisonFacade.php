<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Comparison\Industry;

use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class StockIndustryComparisonFacade
{

	private const COMPARABLE_METRICS = [
		StockValuationTypeEnum::TRAILING_PE,
		StockValuationTypeEnum::FORWARD_PE,
		StockValuationTypeEnum::PEG_RATIO,
		StockValuationTypeEnum::PRICE_SALES,
		StockValuationTypeEnum::PRICE_BOOK,
		StockValuationTypeEnum::EV_EBITDA,
	];

	public function getComparison(StockValuation $stockValuation): StockIndustryComparison
	{
		$stockAsset = $stockValuation->getStockAsset();
		$industry = $stockAsset->getIndustry();

		$metrics = [];

		foreach (self::COMPARABLE_METRICS as $metricType) {
			$stockValue = $this->getStockValue($stockValuation, $metricType);

			if ($stockValue === null) {
				continue;
			}

			$industryAverage = $this->getIndustryAverage($industry, $metricType);
			$state = $this->calculateState($stockValue, $industryAverage);

			$metrics[] = new StockIndustryComparisonMetric(
				$metricType,
				$stockValue,
				$industryAverage,
				$state,
			);
		}

		return new StockIndustryComparison($stockAsset, $metrics);
	}

	private function getStockValue(
		StockValuation $stockValuation,
		StockValuationTypeEnum $metric,
	): float|null
	{
		$data = $stockValuation->getValuationDataByType($metric);

		if ($data === null) {
			return null;
		}

		return $data->getFloatValue();
	}

	private function getIndustryAverage(
		StockAssetIndustry|null $industry,
		StockValuationTypeEnum $metric,
	): float|null
	{
		if ($industry === null) {
			return null;
		}

		return match ($metric) {
			StockValuationTypeEnum::TRAILING_PE => $industry->getCurrentPERatio(),
			StockValuationTypeEnum::FORWARD_PE => $industry->getForwardPERatio(),
			StockValuationTypeEnum::PEG_RATIO => $industry->getPegRatio(),
			StockValuationTypeEnum::PRICE_SALES => $industry->getPriceToSales(),
			StockValuationTypeEnum::PRICE_BOOK => $industry->getPriceToBook(),
			StockValuationTypeEnum::EV_EBITDA => $industry->getPriceToCashFlow(),
			default => null,
		};
	}

	private function calculateState(float $stockValue, float|null $industryAverage): StockIndustryComparisonState
	{
		if ($industryAverage === null || $industryAverage === 0.0) {
			return StockIndustryComparisonState::NO_DATA;
		}

		$deviationPercent = ($stockValue - $industryAverage) / $industryAverage * 100;

		return match (true) {
			$deviationPercent >= 20 => StockIndustryComparisonState::SIGNIFICANTLY_ABOVE,
			$deviationPercent >= 10 => StockIndustryComparisonState::ABOVE_AVERAGE,
			$deviationPercent <= -20 => StockIndustryComparisonState::SIGNIFICANTLY_BELOW,
			$deviationPercent <= -10 => StockIndustryComparisonState::BELOW_AVERAGE,
			default => StockIndustryComparisonState::IN_LINE,
		};
	}

}
