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

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 7.0;

	private float|null $currentEvEbitda = null;

	public function __construct(private float $targetMultiple = 10.0)
	{
	}

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
		)?->getFloatValue();

		$totalCash = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TOTAL_CASH,
		)?->getFloatValue();

		$this->currentEvEbitda = $currentEvEbitda;

		if (
			$ebitda === null
			|| !is_finite($this->targetMultiple)
			|| $this->targetMultiple <= 0
			|| $ebitda <= 0
			|| $sharesOutstanding === null
			|| $sharesOutstanding <= 0
			|| $totalDebt === null
			|| $totalCash === null
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$netDebt = $totalDebt - $totalCash;
		$fairEV = $ebitda * $this->targetMultiple;

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
			description: $this->getDescription(),
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
			new StockValuationModelUsedValue('Předpoklad EV/EBITDA', $this->targetMultiple),
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

	protected function getDescription(): string
	{
		return 'EBITDA × zvolený násobek, minus čistý dluh. Násobek je předpoklad, nikoli dostupný průměr odvětví.';
	}

}
