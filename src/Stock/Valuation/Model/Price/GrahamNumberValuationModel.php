<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class GrahamNumberValuationModel extends BasePriceModel
{

	private const GRAHAM_MULTIPLIER = 22.5;

	private const UNDERPRICED_THRESHOLD = 15.0;

	private const OVERPRICED_THRESHOLD = -15.0;

	private const FAIR_VALUE_THRESHOLD = 8.0;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$dilutedEps = $stockValuation->getValuationDataByType(StockValuationTypeEnum::DILUTED_EPS)?->getFloatValue();
		$bookValuePerShare = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
		)?->getFloatValue();

		if (
			$dilutedEps === null
			|| $dilutedEps <= 0
			|| $bookValuePerShare === null
			|| $bookValuePerShare <= 0
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Graham Number = sqrt(22.5 × EPS × Book Value per Share)
		$fairPrice = sqrt(self::GRAHAM_MULTIPLIER * $dilutedEps * $bookValuePerShare);

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
		return 'Graham Number';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::DILUTED_EPS,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('GRAHAM_MULTIPLIER', self::GRAHAM_MULTIPLIER),
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
