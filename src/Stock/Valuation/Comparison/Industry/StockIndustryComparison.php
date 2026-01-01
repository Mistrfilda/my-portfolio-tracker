<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Comparison\Industry;

use App\Stock\Asset\StockAsset;

class StockIndustryComparison
{

	/**
	 * @param array<StockIndustryComparisonMetric> $metrics
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $metrics,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockIndustryComparisonMetric>
	 */
	public function getMetrics(): array
	{
		return $this->metrics;
	}

	/**
	 * @return array<StockIndustryComparisonMetric>
	 */
	public function getPositiveMetrics(): array
	{
		return array_filter(
			$this->metrics,
			static fn (StockIndustryComparisonMetric $metric) => $metric->isPositive(),
		);
	}

	/**
	 * @return array<StockIndustryComparisonMetric>
	 */
	public function getNegativeMetrics(): array
	{
		return array_filter(
			$this->metrics,
			static fn (StockIndustryComparisonMetric $metric) => !$metric->isPositive()
				&& $metric->getState() !== StockIndustryComparisonState::IN_LINE
				&& $metric->getState() !== StockIndustryComparisonState::NO_DATA,
		);
	}

	public function hasIndustryData(): bool
	{
		foreach ($this->metrics as $metric) {
			if ($metric->hasIndustryData()) {
				return true;
			}
		}

		return false;
	}

	public function getOverallScore(): float|null
	{
		if (!$this->hasIndustryData()) {
			return null;
		}

		$totalPoints = 0;
		$maxPoints = 0;

		foreach ($this->metrics as $metric) {
			if (!$metric->hasIndustryData()) {
				continue;
			}

			$maxPoints += 100;

			$points = match ($metric->getState()) {
				StockIndustryComparisonState::SIGNIFICANTLY_ABOVE,
				StockIndustryComparisonState::SIGNIFICANTLY_BELOW => $metric->isPositive() ? 100 : 0,
				StockIndustryComparisonState::ABOVE_AVERAGE,
				StockIndustryComparisonState::BELOW_AVERAGE => $metric->isPositive() ? 75 : 25,
				StockIndustryComparisonState::IN_LINE => 50,
				StockIndustryComparisonState::NO_DATA => 0,
			};

			$totalPoints += $points;
		}

		if ($maxPoints === 0) {
			return null;
		}

		return round($totalPoints / $maxPoints * 100, 2);
	}

}
