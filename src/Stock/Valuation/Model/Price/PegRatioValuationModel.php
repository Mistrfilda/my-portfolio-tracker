<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class PegRatioValuationModel extends BasePriceModel
{

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$value = $stockValuation->getValuationDataByType(StockValuationTypeEnum::PEG_RATIO)?->getFloatValue();
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
		return 'PEG (očekávaný růst)';
	}

	/** @return array<StockValuationTypeEnum> */
	protected function getUsedTypes(): array
	{
		return [StockValuationTypeEnum::PEG_RATIO];
	}

	/** @return array<StockValuationModelUsedValue> */
	protected function getModelUsedValues(): array
	{
		return [];
	}

	protected function getDescription(): string
	{
		return 'PEG vztahuje P/E k očekávanému růstu. Bez doložené ceny a data výpočtu PEG z něj cenu neodvozujeme.';
	}

}
