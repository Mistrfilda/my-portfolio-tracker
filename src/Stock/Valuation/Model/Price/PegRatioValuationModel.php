<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class PegRatioValuationModel extends BasePriceModel
{

	private const FAIR_PEG_RATIO = 1.0;

	private const UNDERPRICED_THRESHOLD = 15.0;

	private const OVERPRICED_THRESHOLD = -15.0;

	private const FAIR_VALUE_THRESHOLD = 8.0;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$dilutedEps = $stockValuation->getValuationDataByType(StockValuationTypeEnum::DILUTED_EPS)?->getFloatValue();
		$earningsGrowth = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
		)?->getFloatValue();

		if ($dilutedEps === null || $dilutedEps <= 0 || $earningsGrowth === null || $earningsGrowth <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Convert percentage to absolute value (if it's stored as 5.0 for 5%)
		$growthRate = abs($earningsGrowth);

		// Fair P/E based on PEG = 1.0
		$fairPE = self::FAIR_PEG_RATIO * $growthRate;

		// Fair price
		$fairPrice = $dilutedEps * $fairPE;

		$assetPrice = null;
		$percentage = null;
		$state = StockValuationModelState::NEUTRAL;

		if ($currentPrice > 0) {
			$assetPrice = new AssetPrice($stockAsset, $fairPrice, $stockAsset->getCurrency());
			$percentage = ($fairPrice - $currentPrice) / $currentPrice * 100;

			$state = $this->determineState($percentage);
		}

		return new StockValuationPriceModelResponse(
			stockValuationModel: $this,
			stockAsset: $stockAsset,
			assetPrice: $assetPrice,
			calculatedPercentage: $percentage,
			calculatedValue: $fairPrice,
			usedStockValuationDataTypes: $this->getUsedTypes(),
			label: $this->getLabel(),
			state: $state,
			modelUsedValues: $this->getModelUsedValues(),
		);
	}

	protected function getLabel(): string
	{
		return 'PEG Ratio Model';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::DILUTED_EPS,
			StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('FAIR_PEG_RATIO', self::FAIR_PEG_RATIO),
			new StockValuationModelUsedValue('UNDERPRICED_THRESHOLD', self::UNDERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('OVERPRICED_THRESHOLD', self::OVERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('FAIR_VALUE_THRESHOLD', self::FAIR_VALUE_THRESHOLD),
		];
	}

	private function determineState(float|null $percentage): StockValuationModelState
	{
		if ($percentage === null) {
			return StockValuationModelState::NEUTRAL;
		}

		if (abs($percentage) <= self::FAIR_VALUE_THRESHOLD) {
			return StockValuationModelState::FAIR_VALUE;
		}

		if ($percentage >= self::UNDERPRICED_THRESHOLD) {
			return StockValuationModelState::UNDERPRICED;
		}

		if ($percentage <= self::OVERPRICED_THRESHOLD) {
			return StockValuationModelState::OVERPRICED;
		}

		return StockValuationModelState::NEUTRAL;
	}

}
