<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class PriceEarningsValuationModel extends BasePriceModel
{

	private const UNDERPRICED_THRESHOLD = 10.0;

	private const OVERPRICED_THRESHOLD = -10.0;

	private const FAIR_VALUE_THRESHOLD = 5.0;

	private float $industryAveragePe;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$dilutedEps = $stockValuation->getValuationDataByType(StockValuationTypeEnum::DILUTED_EPS)?->getFloatValue();

		$industryAveragePe = null;
		if ($stockAsset->getIndustry() !== null) {
			$industryAveragePe = $stockAsset->getIndustry()->getCurrentPERatio();
		}

		if ($industryAveragePe === null || !is_finite($industryAveragePe) || $industryAveragePe <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$this->industryAveragePe = $industryAveragePe;

		if ($dilutedEps === null || $dilutedEps <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$fairPrice = $dilutedEps * $industryAveragePe;

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
			description: $this->getDescription(),
		);
	}

	protected function getLabel(): string
	{
		return 'P/E podle odvětví';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::DILUTED_EPS,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('Odvětvové P/E', $this->industryAveragePe),
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

	protected function getDescription(): string
	{
		return 'Zisk na akcii × odvětvové P/E. Srovnání tržního ocenění; průměr odvětví nemusí být férový.';
	}

}
