<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Consensus;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Price\BookValueValuationModel;
use App\Stock\Valuation\Model\Price\DebtAdjustedValuationModel;
use App\Stock\Valuation\Model\Price\DividendDiscountValuationModel;
use App\Stock\Valuation\Model\Price\DividendPayoutSafetyModel;
use App\Stock\Valuation\Model\Price\DividendYieldFairValueModel;
use App\Stock\Valuation\Model\Price\EnterpriseValueValuationModel;
use App\Stock\Valuation\Model\Price\FreeCashFlowValuationModel;
use App\Stock\Valuation\Model\Price\GrahamNumberValuationModel;
use App\Stock\Valuation\Model\Price\PegRatioValuationModel;
use App\Stock\Valuation\Model\Price\PriceEarningsValuationModel;
use App\Stock\Valuation\Model\Price\PriceToSalesValuationModel;
use App\Stock\Valuation\Model\Price\RoeQualityValuationModel;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use const SORT_NUMERIC;

class StockValuationModelConsensusProvider
{

	/**
	 * @param array<StockValuationModelResponse> $modelResponses
	 */
	public function getForStockAsset(
		StockAsset $stockAsset,
		array $modelResponses,
	): StockValuationModelConsensus
	{
		$currentPrice = $stockAsset->getAssetCurrentPrice();
		$familyBuckets = [];
		$validModelPrices = [];

		foreach ($modelResponses as $modelResponse) {
			$family = $this->getFamily($modelResponse->getStockValuationModel());
			$familyBuckets[$family->value] ??= [
				'family' => $family,
				'totalModelsCount' => 0,
				'validPrices' => [],
			];
			$familyBuckets[$family->value]['totalModelsCount']++;

			$assetPrice = $modelResponse->getAssetPrice();
			if (
				$modelResponse->getStockValuationModelTrend() === StockValuationModelState::UNABLE_TO_CALCULATE
				|| $assetPrice === null
				|| $assetPrice->getCurrency() !== $currentPrice->getCurrency()
				|| !is_finite($assetPrice->getPrice())
				|| $assetPrice->getPrice() <= 0.0
			) {
				continue;
			}

			$familyBuckets[$family->value]['validPrices'][] = $assetPrice->getPrice();
			$validModelPrices[] = [
				'label' => $modelResponse->getLabel(),
				'price' => $assetPrice->getPrice(),
			];
		}

		ksort($familyBuckets);

		$familyEstimates = [];
		$validFamilyPrices = [];
		foreach ($familyBuckets as $familyBucket) {
			$familyPrice = $familyBucket['validPrices'] === []
				? null
				: $this->median($familyBucket['validPrices']);

			if ($familyPrice !== null) {
				$validFamilyPrices[] = $familyPrice;
			}

			$familyEstimates[] = new StockValuationModelFamilyEstimate(
				$familyBucket['family'],
				$this->createPrice($stockAsset, $familyPrice),
				$this->calculatePercentage($familyPrice, $currentPrice->getPrice()),
				count($familyBucket['validPrices']),
				$familyBucket['totalModelsCount'],
			);
		}

		if ($validFamilyPrices === []) {
			return new StockValuationModelConsensus(
				price: null,
				percentage: null,
				rawMedianPrice: null,
				arithmeticMeanPrice: null,
				lowerQuartilePrice: null,
				upperQuartilePrice: null,
				lowerQuartilePercentage: null,
				upperQuartilePercentage: null,
				normalizedIqrPercentage: null,
				normalizedMadPercentage: null,
				validModelsCount: 0,
				totalModelsCount: count($modelResponses),
				validFamiliesCount: 0,
				totalFamiliesCount: count($familyBuckets),
				outlierModelLabels: [],
				familyEstimates: $familyEstimates,
				confidence: StockValuationModelConsensusConfidenceEnum::UNKNOWN,
			);
		}

		$modelPrices = array_column($validModelPrices, 'price');
		$consensusPrice = $this->median($validFamilyPrices);
		$rawMedianPrice = $this->median($modelPrices);
		$arithmeticMeanPrice = array_sum($modelPrices) / count($modelPrices);
		[$lowerQuartilePrice, $upperQuartilePrice] = $this->quartiles($validFamilyPrices);
		$normalizedIqrPercentage = ($upperQuartilePrice - $lowerQuartilePrice) / $consensusPrice * 100;
		$normalizedMadPercentage = $this->median(array_map(
			static fn (float $price): float => abs($price - $consensusPrice),
			$modelPrices,
		)) / $consensusPrice * 100;
		$outlierModelLabels = $this->getOutlierModelLabels($validModelPrices);
		$validFamiliesCount = count($validFamilyPrices);

		return new StockValuationModelConsensus(
			price: $this->createPrice($stockAsset, $consensusPrice),
			percentage: $this->calculatePercentage($consensusPrice, $currentPrice->getPrice()),
			rawMedianPrice: $this->createPrice($stockAsset, $rawMedianPrice),
			arithmeticMeanPrice: $this->createPrice($stockAsset, $arithmeticMeanPrice),
			lowerQuartilePrice: $this->createPrice($stockAsset, $lowerQuartilePrice),
			upperQuartilePrice: $this->createPrice($stockAsset, $upperQuartilePrice),
			lowerQuartilePercentage: $this->calculatePercentage($lowerQuartilePrice, $currentPrice->getPrice()),
			upperQuartilePercentage: $this->calculatePercentage($upperQuartilePrice, $currentPrice->getPrice()),
			normalizedIqrPercentage: $normalizedIqrPercentage,
			normalizedMadPercentage: $normalizedMadPercentage,
			validModelsCount: count($modelPrices),
			totalModelsCount: count($modelResponses),
			validFamiliesCount: $validFamiliesCount,
			totalFamiliesCount: count($familyBuckets),
			outlierModelLabels: $outlierModelLabels,
			familyEstimates: $familyEstimates,
			confidence: $this->getConfidence($validFamiliesCount, $normalizedIqrPercentage),
		);
	}

	private function getFamily(StockValuationModel $model): StockValuationModelFamilyEnum
	{
		return match (true) {
			$model instanceof BookValueValuationModel,
			$model instanceof DebtAdjustedValuationModel,
			$model instanceof RoeQualityValuationModel,
			$model instanceof GrahamNumberValuationModel => StockValuationModelFamilyEnum::ASSET,
			$model instanceof PriceEarningsValuationModel,
			$model instanceof PegRatioValuationModel => StockValuationModelFamilyEnum::EARNINGS,
			$model instanceof FreeCashFlowValuationModel,
			$model instanceof EnterpriseValueValuationModel => StockValuationModelFamilyEnum::CASH_FLOW,
			$model instanceof PriceToSalesValuationModel => StockValuationModelFamilyEnum::SALES,
			$model instanceof DividendDiscountValuationModel,
			$model instanceof DividendPayoutSafetyModel,
			$model instanceof DividendYieldFairValueModel => StockValuationModelFamilyEnum::DIVIDEND,
			default => StockValuationModelFamilyEnum::OTHER,
		};
	}

	/**
	 * @param array<float> $values
	 */
	private function median(array $values): float
	{
		sort($values, SORT_NUMERIC);
		$count = count($values);
		$middle = intdiv($count, 2);

		if ($count % 2 === 1) {
			return $values[$middle];
		}

		return ($values[$middle - 1] + $values[$middle]) / 2;
	}

	/**
	 * @param array<float> $values
	 * @return array{float, float}
	 */
	private function quartiles(array $values): array
	{
		sort($values, SORT_NUMERIC);
		$count = count($values);
		if ($count === 1) {
			return [$values[0], $values[0]];
		}

		$middle = intdiv($count, 2);
		$lowerValues = array_slice($values, 0, $middle);
		$upperValues = array_slice($values, $count % 2 === 0 ? $middle : $middle + 1);

		return [$this->median($lowerValues), $this->median($upperValues)];
	}

	/**
	 * @param array<array{label: string, price: float}> $modelPrices
	 * @return array<string>
	 */
	private function getOutlierModelLabels(array $modelPrices): array
	{
		if (count($modelPrices) < 2) {
			return [];
		}

		[$lowerQuartile, $upperQuartile] = $this->quartiles(array_column($modelPrices, 'price'));
		$interquartileRange = $upperQuartile - $lowerQuartile;
		$lowerFence = $lowerQuartile - 1.5 * $interquartileRange;
		$upperFence = $upperQuartile + 1.5 * $interquartileRange;
		$outlierLabels = [];

		foreach ($modelPrices as $modelPrice) {
			if ($modelPrice['price'] < $lowerFence || $modelPrice['price'] > $upperFence) {
				$outlierLabels[] = $modelPrice['label'];
			}
		}

		return array_values(array_unique($outlierLabels));
	}

	private function createPrice(StockAsset $stockAsset, float|null $price): AssetPrice|null
	{
		if ($price === null) {
			return null;
		}

		return new AssetPrice($stockAsset, $price, $stockAsset->getAssetCurrentPrice()->getCurrency());
	}

	private function calculatePercentage(float|null $price, float $currentPrice): float|null
	{
		if ($price === null || $currentPrice <= 0.0) {
			return null;
		}

		return ($price - $currentPrice) / $currentPrice * 100;
	}

	private function getConfidence(
		int $validFamiliesCount,
		float $normalizedIqrPercentage,
	): StockValuationModelConsensusConfidenceEnum
	{
		if ($validFamiliesCount >= 4 && $normalizedIqrPercentage <= 25.0) {
			return StockValuationModelConsensusConfidenceEnum::HIGH;
		}

		if ($validFamiliesCount >= 3 && $normalizedIqrPercentage <= 50.0) {
			return StockValuationModelConsensusConfidenceEnum::MEDIUM;
		}

		return StockValuationModelConsensusConfidenceEnum::LOW;
	}

}
