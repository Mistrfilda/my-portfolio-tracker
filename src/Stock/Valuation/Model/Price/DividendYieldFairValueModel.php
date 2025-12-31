<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class DividendYieldFairValueModel extends BasePriceModel
{

	/**
	 * Cílový fair dividend yield pro stabilní dividendové akcie
	 * Pokud je aktuální yield vyšší, akcie může být podhodnocená
	 */
	private const TARGET_DIVIDEND_YIELD = 3.5;

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 6.0;

	private float|null $currentYield = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();

		$forwardDividendRate = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
		)?->getFloatValue();

		$trailingDividendRate = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
		)?->getFloatValue();

		$currentYield = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
		)?->getFloatValue();

		$this->currentYield = $currentYield;

		$dividendRate = $forwardDividendRate ?? $trailingDividendRate;

		if (
			$stockAsset->doesPaysDividends() === false
			|| $dividendRate === null
			|| $dividendRate <= 0
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Fair price = roční dividenda / cílový yield
		// Pokud akcie platí $4 dividendu a cílový yield je 3.5%,
		// fair price = 4 / 0.035 = $114.29
		$fairPrice = $dividendRate / (self::TARGET_DIVIDEND_YIELD / 100);

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
		return 'Dividend Yield Fair Value';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_YIELD,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('TARGET_DIVIDEND_YIELD (%)', self::TARGET_DIVIDEND_YIELD),
			new StockValuationModelUsedValue('UNDERPRICED_THRESHOLD', self::UNDERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('OVERPRICED_THRESHOLD', self::OVERPRICED_THRESHOLD),
		];

		if ($this->currentYield !== null) {
			$values[] = new StockValuationModelUsedValue('Current Yield (%)', $this->currentYield);
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
		//phpcs:disable
		return 'Vypočítá férovou cenu na základě cílového dividendového výnosu. Pokud je aktuální yield vyšší než cílový, akcie může být podhodnocená.';
		//phpcs:enable
	}

}
