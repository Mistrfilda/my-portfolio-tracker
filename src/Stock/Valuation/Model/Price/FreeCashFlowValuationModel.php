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

	private const UNDERPRICED_THRESHOLD = 10.0;

	private const OVERPRICED_THRESHOLD = -10.0;

	private const FAIR_VALUE_THRESHOLD = 5.0;

	public function __construct(
		private float $requiredReturn = 0.10,
		private float $growthRate = 0.03,
	)
	{
	}

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

		if (
			!is_finite($this->requiredReturn)
			|| !is_finite($this->growthRate)
			|| $this->requiredReturn <= 0
			|| $this->growthRate <= -1
			|| $this->requiredReturn <= $this->growthRate
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Intrinsic value = FCF * (1 + g) / (r - g)
		$expectedFcf = $fcfPerShare * (1 + $this->growthRate);
		$fairPrice = $expectedFcf / ($this->requiredReturn - $this->growthRate);

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
			scenarios: $this->getPerpetuityScenarios(
				$stockValuation,
				$fcfPerShare,
				false,
				$this->requiredReturn,
				$this->growthRate,
			),
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
			new StockValuationModelUsedValue('Požadovaná výnosnost (%)', $this->requiredReturn * 100),
			new StockValuationModelUsedValue('Dlouhodobý růst (%)', $this->growthRate * 100),
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
		return 'Příští FCF na akcii / (výnosnost − růst). Aproximace FCFE; poslední cash flow nemusí být dlouhodobě udržitelné.';
	}

}
