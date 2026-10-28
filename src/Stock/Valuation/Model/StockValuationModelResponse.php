<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\StockValuationTypeEnum;

interface StockValuationModelResponse
{

	public function getStockValuationModel(): StockValuationModel;

	public function getStockAsset(): StockAsset;

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	public function getUsedStockValuationDataTypes(): array;

	public function getLabel(): string;

	public function getAssetPrice(): AssetPrice|null;

	public function getCalculatedPercentage(): float|null;

	public function getCalculatedValue(): float|null;

	public function getStockValuationModelTrend(): StockValuationModelState;

	/** @return array<StockValuationModelUsedValue> */
	public function getModelUsedValues(): array;

	public function getDescription(): string|null;

	public function getPurpose(): StockValuationModelPurpose;

	public function getAvailability(): StockValuationModelAvailability;

	public function getAvailabilityReason(): string|null;

	public function getValueUnit(): string;

	public function isCalculated(): bool;

	public function getColor(): string;

	/** @return array<StockValuationScenario> */
	public function getScenarios(): array;

}
