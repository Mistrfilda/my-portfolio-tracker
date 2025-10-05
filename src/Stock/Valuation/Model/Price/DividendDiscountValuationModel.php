<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class DividendDiscountValuationModel extends BasePriceModel
{

	private const REQUIRED_RETURN = 0.10;

	private const GROWTH_RATE = 0.03;

	private const UNDERPRICED_THRESHOLD = 10.0;

	private const OVERPRICED_THRESHOLD = -10.0;

	private const FAIR_VALUE_THRESHOLD = 5.0;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();
		$forwardDividendRate = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
		)?->getFloatValue();
		$trailingDividendRate = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TRAILING_ANNUAL_DIVIDEND_RATE,
		)?->getFloatValue();

		if (
			$stockAsset->doesPaysDividends() === false
			|| ($forwardDividendRate === null && $trailingDividendRate === null)
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$dividendRate = $forwardDividendRate ?? $trailingDividendRate;

		// Gordon Growth Model: P = D / (r - g)
		// kde D = očekávaná dividenda, r = požadovaná návratnost, g = růst
		if ($dividendRate <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// @phpstan-ignore-next-line
		if (self::REQUIRED_RETURN <= self::GROWTH_RATE) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		$expectedDividend = $dividendRate * (1 + self::GROWTH_RATE);
		$fairPrice = $expectedDividend / (self::REQUIRED_RETURN - self::GROWTH_RATE);

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
		);
	}

	protected function getLabel(): string
	{
		return 'Dividend Discount Model (DDM)';
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
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		return [
			new StockValuationModelUsedValue('REQUIRED_RETURN', self::REQUIRED_RETURN),
			new StockValuationModelUsedValue('GROWTH_RATE', self::GROWTH_RATE),
			new StockValuationModelUsedValue('UNDERPRICED_THRESHOLD', self::UNDERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('OVERPRICED_THRESHOLD', self::OVERPRICED_THRESHOLD),
			new StockValuationModelUsedValue('FAIR_VALUE_THRESHOLD', self::FAIR_VALUE_THRESHOLD),
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

}
