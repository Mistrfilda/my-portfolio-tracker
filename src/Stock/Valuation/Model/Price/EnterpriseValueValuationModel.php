<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class EnterpriseValueValuationModel extends BasePriceModel
{

	private const FAIR_EV_EBITDA_RATIO = 10.0;

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 7.0;

	private float|null $currentEvEbitda = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();

		// Použijeme existující EV/EBITDA ratio
		$currentEvEbitda = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::EV_EBITDA,
		)?->getFloatValue();

		$ebitda = $stockValuation->getValuationDataByType(StockValuationTypeEnum::EBITDA)?->getFloatValue();
		$sharesOutstanding = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::SHARES_OUTSTANDING,
		)?->getFloatValue();

		$totalDebt = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TOTAL_DEBT,
		)?->getFloatValue() ?? 0.0;

		$totalCash = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TOTAL_CASH,
		)?->getFloatValue() ?? 0.0;

		$netDebt = $totalDebt - $totalCash;

		$this->currentEvEbitda = $currentEvEbitda;

		if ($ebitda === null || $ebitda <= 0 || $sharesOutstanding === null || $sharesOutstanding <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$fairEV = $ebitda * self::FAIR_EV_EBITDA_RATIO;

		$fairEquityValue = $fairEV - $netDebt;

		if ($fairEquityValue <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$fairPrice = $fairEquityValue / $sharesOutstanding;

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
		return 'EV/EBITDA Model';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::EBITDA,
			StockValuationTypeEnum::SHARES_OUTSTANDING,
			StockValuationTypeEnum::EV_EBITDA,
			StockValuationTypeEnum::TOTAL_DEBT,
			StockValuationTypeEnum::TOTAL_CASH,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('FAIR_EV_EBITDA_RATIO', self::FAIR_EV_EBITDA_RATIO),
			new StockValuationModelUsedValue('UNDERPRICED_THRESHOLD', self::UNDERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('OVERPRICED_THRESHOLD', self::OVERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('FAIR_VALUE_THRESHOLD', self::FAIR_VALUE_THRESHOLD),
		];

		if ($this->currentEvEbitda !== null) {
			$values[] = new StockValuationModelUsedValue('Current EV/EBITDA', $this->currentEvEbitda);
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

}
