<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\MarginOfSafety;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;

class StockValuationMarginOfSafetyProvider
{

	public function getForStockAsset(
		StockAsset $stockAsset,
		AssetPrice|null $averageModelPrice,
		AssetPrice|null $analyticsPrice,
		AssetPrice|null $aiAnalysisPrice,
	): StockValuationMarginOfSafety
	{
		$currentPrice = $stockAsset->getAssetCurrentPrice();
		$sourcePrices = [];
		$reasons = [];

		foreach ([$averageModelPrice, $analyticsPrice, $aiAnalysisPrice] as $sourcePrice) {
			if ($sourcePrice === null) {
				continue;
			}

			if ($sourcePrice->getCurrency() !== $currentPrice->getCurrency()) {
				$reasons[] = sprintf(
					'Source in %s was ignored because current price is in %s.',
					$sourcePrice->getCurrency()->value,
					$currentPrice->getCurrency()->value,
				);

				continue;
			}

			$sourcePrices[] = $sourcePrice->getPrice();
		}

		$sourcesCount = count($sourcePrices);
		if ($sourcesCount === 0 || $currentPrice->getPrice() <= 0.0) {
			return new StockValuationMarginOfSafety(
				null,
				null,
				null,
				$sourcesCount,
				StockValuationMarginOfSafetyStatusEnum::UNKNOWN,
				StockValuationMarginOfSafetyConfidenceEnum::UNKNOWN,
				$reasons === [] ? ['No comparable fair price sources are available.'] : $reasons,
			);
		}

		$fairPrice = array_sum($sourcePrices) / $sourcesCount;
		$marginPercentage = ($fairPrice - $currentPrice->getPrice()) / $currentPrice->getPrice() * 100;
		$sourceSpreadPercentage = $this->calculateSourceSpreadPercentage($sourcePrices, $fairPrice);

		return new StockValuationMarginOfSafety(
			new AssetPrice($stockAsset, $fairPrice, $currentPrice->getCurrency()),
			$marginPercentage,
			$sourceSpreadPercentage,
			$sourcesCount,
			$this->getStatus($marginPercentage),
			$this->getConfidence($sourcesCount, $sourceSpreadPercentage),
			$reasons,
		);
	}

	/**
	 * @param array<float> $sourcePrices
	 */
	private function calculateSourceSpreadPercentage(array $sourcePrices, float $fairPrice): float|null
	{
		if (count($sourcePrices) < 2 || $fairPrice <= 0.0) {
			return null;
		}

		return (max($sourcePrices) - min($sourcePrices)) / $fairPrice * 100;
	}

	private function getStatus(float $marginPercentage): StockValuationMarginOfSafetyStatusEnum
	{
		if ($marginPercentage >= 15.0) {
			return StockValuationMarginOfSafetyStatusEnum::UNDERVALUED;
		}

		if ($marginPercentage <= -15.0) {
			return StockValuationMarginOfSafetyStatusEnum::OVERVALUED;
		}

		return StockValuationMarginOfSafetyStatusEnum::FAIR;
	}

	private function getConfidence(
		int $sourcesCount,
		float|null $sourceSpreadPercentage,
	): StockValuationMarginOfSafetyConfidenceEnum
	{
		if ($sourcesCount >= 3 && $sourceSpreadPercentage !== null && $sourceSpreadPercentage <= 15.0) {
			return StockValuationMarginOfSafetyConfidenceEnum::HIGH;
		}

		if ($sourcesCount >= 2 && ($sourceSpreadPercentage === null || $sourceSpreadPercentage <= 30.0)) {
			return StockValuationMarginOfSafetyConfidenceEnum::MEDIUM;
		}

		return StockValuationMarginOfSafetyConfidenceEnum::LOW;
	}

}
