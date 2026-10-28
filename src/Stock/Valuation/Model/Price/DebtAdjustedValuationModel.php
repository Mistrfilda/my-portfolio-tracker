<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class DebtAdjustedValuationModel extends BasePriceModel
{

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$value = $stockValuation->getValuationDataByType(StockValuationTypeEnum::TOTAL_DEBT_EQUITY)?->getFloatValue();
		if ($value === null || !is_finite($value)) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		return new StockValuationPriceModelResponse(
			stockValuationModel: $this,
			stockAsset: $stockValuation->getStockAsset(),
			assetPrice: null,
			calculatedPercentage: null,
			calculatedValue: $value,
			usedStockValuationDataTypes: $this->getUsedTypes(),
			label: $this->getLabel(),
			state: StockValuationModelState::NEUTRAL,
			description: $this->getDescription(),
		);
	}

	protected function getLabel(): string
	{
		return 'Zadlužení (dluh / vlastní kapitál)';
	}

	/** @return array<StockValuationTypeEnum> */
	protected function getUsedTypes(): array
	{
		return [StockValuationTypeEnum::TOTAL_DEBT_EQUITY];
	}

	/** @return array<StockValuationModelUsedValue> */
	protected function getModelUsedValues(): array
	{
		return [];
	}

	protected function getDescription(): string
	{
		return 'Dluh vůči vlastnímu kapitálu v procentech. Posuzujte s odvětvím; nejde o odhad ceny.';
	}

}
