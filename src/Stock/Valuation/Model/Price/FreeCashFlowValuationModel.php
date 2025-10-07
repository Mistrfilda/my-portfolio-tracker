<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class FreeCashFlowValuationModel extends BasePriceModel
{

	private const REQUIRED_RETURN = 0.10;

	private const FCF_GROWTH_RATE = 0.03;

	private const UNDERPRICED_THRESHOLD = 10.0;

	private const OVERPRICED_THRESHOLD = -10.0;

	private const FAIR_VALUE_THRESHOLD = 5.0;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();

		$leveredFreeCashFlow = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
		)?->getFloatValue();

		$sharesOutstanding = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::SHARES_OUTSTANDING,
		)?->getFloatValue();

		if ($leveredFreeCashFlow === null || $sharesOutstanding === null || $sharesOutstanding <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// FCF per share
		$fcfPerShare = $leveredFreeCashFlow / $sharesOutstanding;

		if ($fcfPerShare <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// @phpstan-ignore-next-line
		if (self::REQUIRED_RETURN <= self::FCF_GROWTH_RATE) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Intrinsic value = FCF * (1 + g) / (r - g)
		$expectedFcf = $fcfPerShare * (1 + self::FCF_GROWTH_RATE);
		$fairPrice = $expectedFcf / (self::REQUIRED_RETURN - self::FCF_GROWTH_RATE);

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
		return 'Free Cash Flow to Equity (FCFE)';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
			StockValuationTypeEnum::SHARES_OUTSTANDING,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('REQUIRED_RETURN', self::REQUIRED_RETURN),
			new StockValuationModelUsedValue('FCF_GROWTH_RATE', self::FCF_GROWTH_RATE),
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
