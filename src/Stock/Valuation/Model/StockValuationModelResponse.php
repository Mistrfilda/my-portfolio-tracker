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

}
