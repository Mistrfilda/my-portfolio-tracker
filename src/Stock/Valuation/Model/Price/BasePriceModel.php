<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

abstract class BasePriceModel implements StockValuationModel
{

	protected function getUnableToCalculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		return new StockValuationPriceModelResponse(
			stockValuationModel: $this,
			stockAsset: $stockValuation->getStockAsset(),
			assetPrice: null,
			calculatedPercentage: null,
			calculatedValue: null,
			usedStockValuationDataTypes: $this->getUsedTypes(),
			label: $this->getLabel(),
			state: StockValuationModelState::UNABLE_TO_CALCULATE,
		);
	}

	abstract protected function getLabel(): string;

	/** @return array<StockValuationTypeEnum> */
	abstract protected function getUsedTypes(): array;

	/** @return array<StockValuationModelUsedValue> */
	abstract protected function getModelUsedValues(): array;

}
