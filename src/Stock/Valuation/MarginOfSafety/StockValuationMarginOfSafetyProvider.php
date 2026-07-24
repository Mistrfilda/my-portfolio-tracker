<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\MarginOfSafety;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusConfidenceEnum;

class StockValuationMarginOfSafetyProvider
{

	public function getForStockAsset(
		StockAsset $stockAsset,
		StockValuationModelConsensus $modelConsensus,
		AssetPrice|null $analyticsPrice,
		AssetPrice|null $aiAnalysisPrice,
	): StockValuationMarginOfSafety
	{
		$currentPrice = $stockAsset->getAssetCurrentPrice();
		$reasons = [];
		$inputEstimatesCount = 0;
		$groupPrices = [];

		$modelPrice = $this->getComparablePrice(
			$modelConsensus->getPrice(),
			'Modelový konsenzus',
			$currentPrice,
			$reasons,
		);
		if ($modelPrice !== null) {
			$groupPrices[] = $modelPrice;
			$inputEstimatesCount++;
		}

		$externalPrices = [];
		foreach (['Analytici' => $analyticsPrice, 'AI' => $aiAnalysisPrice] as $label => $sourcePrice) {
			$comparablePrice = $this->getComparablePrice(
				$sourcePrice,
				$label,
				$currentPrice,
				$reasons,
			);
			if ($comparablePrice === null) {
				continue;
			}

			$externalPrices[] = $comparablePrice;
			$inputEstimatesCount++;
		}

		$externalEstimate = null;
		if ($externalPrices !== []) {
			$externalEstimateValue = array_sum($externalPrices) / count($externalPrices);
			$externalEstimate = new AssetPrice(
				$stockAsset,
				$externalEstimateValue,
				$currentPrice->getCurrency(),
			);
			$groupPrices[] = $externalEstimateValue;
		}

		$sourceGroupsCount = count($groupPrices);
		if ($sourceGroupsCount === 0 || $currentPrice->getPrice() <= 0.0) {
			return new StockValuationMarginOfSafety(
				null,
				null,
				null,
				$externalEstimate,
				$sourceGroupsCount,
				$inputEstimatesCount,
				StockValuationMarginOfSafetyStatusEnum::UNKNOWN,
				StockValuationMarginOfSafetyConfidenceEnum::UNKNOWN,
				$reasons === [] ? ['Nejsou dostupné žádné porovnatelné zdroje férové ceny.'] : $reasons,
			);
		}

		$fairPrice = array_sum($groupPrices) / $sourceGroupsCount;
		$marginPercentage = ($fairPrice - $currentPrice->getPrice()) / $currentPrice->getPrice() * 100;
		$sourceSpreadPercentage = $this->calculateSourceSpreadPercentage($groupPrices, $fairPrice);

		return new StockValuationMarginOfSafety(
			new AssetPrice($stockAsset, $fairPrice, $currentPrice->getCurrency()),
			$marginPercentage,
			$sourceSpreadPercentage,
			$externalEstimate,
			$sourceGroupsCount,
			$inputEstimatesCount,
			$this->getStatus($marginPercentage),
			$this->getConfidence(
				$sourceGroupsCount,
				$sourceSpreadPercentage,
				$modelConsensus->getConfidence(),
			),
			$reasons,
		);
	}

	/**
	 * @param array<string> $reasons
	 */
	private function getComparablePrice(
		AssetPrice|null $sourcePrice,
		string $label,
		AssetPrice $currentPrice,
		array &$reasons,
	): float|null
	{
		if ($sourcePrice === null) {
			return null;
		}

		if ($sourcePrice->getCurrency() !== $currentPrice->getCurrency()) {
			$reasons[] = sprintf(
				'%s v měně %s byl vynechán, protože aktuální cena je v měně %s.',
				$label,
				$sourcePrice->getCurrency()->value,
				$currentPrice->getCurrency()->value,
			);

			return null;
		}

		if (!is_finite($sourcePrice->getPrice()) || $sourcePrice->getPrice() <= 0.0) {
			$reasons[] = sprintf('%s byl vynechán, protože neobsahuje platnou kladnou cenu.', $label);

			return null;
		}

		return $sourcePrice->getPrice();
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
		int $sourceGroupsCount,
		float|null $sourceSpreadPercentage,
		StockValuationModelConsensusConfidenceEnum $modelConfidence,
	): StockValuationMarginOfSafetyConfidenceEnum
	{
		if (
			$sourceGroupsCount === 2
			&& $sourceSpreadPercentage !== null
			&& $sourceSpreadPercentage <= 15.0
			&& $modelConfidence === StockValuationModelConsensusConfidenceEnum::HIGH
		) {
			return StockValuationMarginOfSafetyConfidenceEnum::HIGH;
		}

		if (
			$sourceGroupsCount === 2
			&& $sourceSpreadPercentage !== null
			&& $sourceSpreadPercentage <= 30.0
			&& in_array(
				$modelConfidence,
				[
					StockValuationModelConsensusConfidenceEnum::HIGH,
					StockValuationModelConsensusConfidenceEnum::MEDIUM,
				],
				true,
			)
		) {
			return StockValuationMarginOfSafetyConfidenceEnum::MEDIUM;
		}

		return StockValuationMarginOfSafetyConfidenceEnum::LOW;
	}

}
