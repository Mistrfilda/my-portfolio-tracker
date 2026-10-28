<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelAvailability;
use App\Stock\Valuation\Model\StockValuationModelPurpose;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\Model\StockValuationScenario;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;

class StockValuationPriceModelResponse implements StockValuationModelResponse
{

	/**
	 * @param array<StockValuationTypeEnum> $usedStockValuationDataTypes
	 * @param array<StockValuationModelUsedValue> $modelUsedValues
	 * @param array<StockValuationScenario> $scenarios
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
		private StockValuationModelAvailability|null $availability = null,
		private string|null $availabilityReason = null,
		private array $scenarios = [],
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
		return $this->getAvailability() === StockValuationModelAvailability::AVAILABLE;
	}

	public function getPurpose(): StockValuationModelPurpose
	{
		return StockValuationModelPurpose::forModel($this->stockValuationModel);
	}

	public function getAvailability(): StockValuationModelAvailability
	{
		return $this->availability ?? ($this->state === StockValuationModelState::UNABLE_TO_CALCULATE
			? StockValuationModelAvailability::MISSING_DATA
			: StockValuationModelAvailability::AVAILABLE);
	}

	public function getAvailabilityReason(): string|null
	{
		return $this->availabilityReason;
	}

	public function getValueUnit(): string
	{
		return ($this->usedStockValuationDataTypes[0] ?? null)?->getTypeValueType() === StockValuationTypeValueTypeEnum::PERCENTAGE
			? '%'
			: '×';
	}

	/** @return array<StockValuationScenario> */
	public function getScenarios(): array
	{
		return $this->scenarios;
	}

	public function withAvailability(
		StockValuationModelAvailability $availability,
		string $reason,
	): self
	{
		$response = clone $this;
		$response->availability = $availability;
		$response->availabilityReason = $reason;
		if ($availability !== StockValuationModelAvailability::AVAILABLE) {
			$response->assetPrice = null;
			$response->calculatedPercentage = null;
			$response->calculatedValue = null;
			$response->scenarios = [];
			$response->state = StockValuationModelState::UNABLE_TO_CALCULATE;
		}

		return $response;
	}

	public function getColor(): string
	{
		return $this->state->getTailwindColor();
	}

}
