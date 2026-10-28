<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class PriceToSalesValuationModel extends BasePriceModel
{

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 7.0;

	private float $industryMultiple = 0.0;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$multiple = $stockAsset->getIndustry()?->getPriceToSales();
		if ($multiple === null || !is_finite($multiple) || $multiple <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$this->industryMultiple = $multiple;
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$revenuePerShare = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::REVENUE_PER_SHARE,
		)?->getFloatValue();

		if ($revenuePerShare === null || $revenuePerShare <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$fairPrice = $revenuePerShare * $this->industryMultiple;

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
		return 'P/S podle odvětví';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::REVENUE_PER_SHARE,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('Odvětvový násobek', $this->industryMultiple),
		];
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
		return 'Tržby na akcii × odvětvové P/S. Doplňkové srovnání nezohledňuje marže ani zadlužení.';
	}

}
