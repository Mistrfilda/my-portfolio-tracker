<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;

class StockValuationModelTableControlItem
{

	/**
	 * @param array<StockValuationModelResponse> $modelResponses
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $modelResponses,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockValuationModelResponse>
	 */
	public function getModelResponses(): array
	{
		return $this->modelResponses;
	}

	public function getCalculatedModelsCount(): int
	{
		return count(array_filter(
			$this->modelResponses,
			static fn (StockValuationModelResponse $modelResponse): bool => $modelResponse->getStockValuationModelTrend()
				!== StockValuationModelState::UNABLE_TO_CALCULATE,
		));
	}

	public function getModelsCount(): int
	{
		return count($this->modelResponses);
	}

	public function getAverageModelPrice(): AssetPrice|null
	{
		$currentPrice = $this->stockAsset->getAssetCurrentPrice();
		$prices = [];

		foreach ($this->modelResponses as $modelResponse) {
			$assetPrice = $modelResponse->getAssetPrice();
			if ($assetPrice === null || $assetPrice->getCurrency() !== $currentPrice->getCurrency()) {
				continue;
			}

			$prices[] = $assetPrice->getPrice();
		}

		if ($prices === []) {
			return null;
		}

		return new AssetPrice(
			$this->stockAsset,
			array_sum($prices) / count($prices),
			$currentPrice->getCurrency(),
		);
	}

	public function getAveragePercentage(): float|null
	{
		$percentages = $this->getCalculatedPercentages();

		return $percentages === [] ? null : array_sum($percentages) / count($percentages);
	}

	public function getMinimumPercentage(): float|null
	{
		$percentages = $this->getCalculatedPercentages();

		return $percentages === [] ? null : min($percentages);
	}

	public function getMaximumPercentage(): float|null
	{
		$percentages = $this->getCalculatedPercentages();

		return $percentages === [] ? null : max($percentages);
	}

	/**
	 * @return array<float>
	 */
	private function getCalculatedPercentages(): array
	{
		$percentages = [];

		foreach ($this->modelResponses as $modelResponse) {
			$percentage = $modelResponse->getCalculatedPercentage();
			if (
				$modelResponse->getStockValuationModelTrend() === StockValuationModelState::UNABLE_TO_CALCULATE
				|| $percentage === null
			) {
				continue;
			}

			$percentages[] = $percentage;
		}

		return $percentages;
	}

}
