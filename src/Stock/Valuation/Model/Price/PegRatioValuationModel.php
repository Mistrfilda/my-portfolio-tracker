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

	private float|null $currentPegRatio = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$currentPegRatio = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::PEG_RATIO,
		)?->getFloatValue();
		$this->currentPegRatio = $currentPegRatio;

		if ($currentPrice <= 0.0 || $currentPegRatio === null || $currentPegRatio <= 0.0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$fairPrice = $currentPrice * self::FAIR_PEG_RATIO / $currentPegRatio;
		$assetPrice = new AssetPrice($stockAsset, $fairPrice, $stockAsset->getCurrency());
		$percentage = ($fairPrice - $currentPrice) / $currentPrice * 100;
		$state = $this->determineState($percentage);

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
		return 'PEG Ratio Model';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::PEG_RATIO,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('FAIR_PEG_RATIO', self::FAIR_PEG_RATIO),
			new StockValuationModelUsedValue('UNDERPRICED_THRESHOLD', self::UNDERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('OVERPRICED_THRESHOLD', self::OVERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('FAIR_VALUE_THRESHOLD', self::FAIR_VALUE_THRESHOLD),
		];

		if ($this->currentPegRatio !== null) {
			$values[] = new StockValuationModelUsedValue('Current PEG Ratio', $this->currentPegRatio);
		}

		return $values;
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
		//phpcs:disable
		return 'Porovnává aktuální pětileté očekávané PEG s férovou hodnotou 1. Férovou cenu odvozuje poměrem cílového a aktuálního PEG, takže nepoužívá krátkodobý růst jako cenový násobek.';
		//phpcs:enable
	}

}
