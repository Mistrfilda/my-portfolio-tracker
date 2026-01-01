<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuationTypeEnum;

class StockValuationPriceModelResponse implements StockValuationModelResponse
{

	/**
	 * @param array<StockValuationTypeEnum> $usedStockValuationDataTypes
	 * @param array<StockValuationModelUsedValue> $modelUsedValues
	 */
	public function __construct(
		private StockValuationModel $stockValuationModel,
		private StockAsset $stockAsset,
		private AssetPrice|null $assetPrice,
		private float|null $calculatedPercentage,
		private float|null $calculatedValue,
		private array $usedStockValuationDataTypes,
		private string $label,
		private StockValuationModelState $state,
		private array $modelUsedValues = [],
		private string|null $description = null,
	)
	{
	}

	public function getStockValuationModel(): StockValuationModel
	{
		return $this->stockValuationModel;
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getAssetPrice(): AssetPrice|null
	{
		return $this->assetPrice;
	}

	public function getCalculatedPercentage(): float|null
	{
		return $this->calculatedPercentage;
	}

	public function getCalculatedValue(): float|null
	{
		return $this->calculatedValue;
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	public function getUsedStockValuationDataTypes(): array
	{
		return $this->usedStockValuationDataTypes;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getStockValuationModelTrend(): StockValuationModelState
	{
		return $this->state;
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	public function getModelUsedValues(): array
	{
		return $this->modelUsedValues;
	}

	public function getDescription(): string|null
	{
		//phpcs:disable
		return $this->description;
		//phpcs:enable
	}

	public function isCalculated(): bool
	{
		return $this->state !== StockValuationModelState::UNABLE_TO_CALCULATE;
	}

	public function getColor(): string
	{
		return $this->state->getTailwindColor();
	}

}
