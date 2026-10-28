<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModel;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\Model\StockValuationScenario;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

abstract class BasePriceModel implements StockValuationModel
{

	/** @return array<StockValuationScenario> */
	protected function getPerpetuityScenarios(
		StockValuation $valuation,
		float $cashFlow,
		bool $isForward,
		float $requiredReturn,
		float $growth,
	): array
	{
		$scenarios = [];
		foreach ([['Opatrný', 0.02, -0.01], ['Základní', 0.0, 0.0], ['Příznivý', -0.02, 0.01]] as [$label, $returnChange, $growthChange]) {
			$r = $requiredReturn + $returnChange;
			$g = $growth + $growthChange;
			if ($r <= $g || $r <= 0 || $g <= -1) {
				continue;
			}

			$price = ($isForward ? $cashFlow : $cashFlow * (1 + $g)) / ($r - $g);
			if (!is_finite($price) || $price <= 0) {
				continue;
			}

			$asset = $valuation->getStockAsset();
			$scenarios[] = new StockValuationScenario(
				$label,
				new AssetPrice($asset, $price, $asset->getCurrency()),
				$r,
				$g,
			);
		}

		return $scenarios;
	}

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
			description: $this->getDescription(),
		);
	}

	abstract protected function getLabel(): string;

	/** @return array<StockValuationTypeEnum> */
	abstract protected function getUsedTypes(): array;

	/** @return array<StockValuationModelUsedValue> */
	abstract protected function getModelUsedValues(): array;

	abstract protected function getDescription(): string;

}
