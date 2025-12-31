<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class DebtAdjustedValuationModel extends BasePriceModel
{

	/**
	 * Dobrá úroveň Debt/Equity ratio
	 */
	private const GOOD_DEBT_EQUITY_RATIO = 0.3;

	/**
	 * Přijatelná úroveň Debt/Equity
	 */
	private const ACCEPTABLE_DEBT_EQUITY = 1.5;

	/**
	 * Vysoká úroveň Debt/Equity
	 */
	private const HIGH_DEBT_EQUITY = 3.0;

	/**
	 * Optimální Current Ratio
	 */
	private const OPTIMAL_CURRENT_RATIO = 2.0;

	/**
	 * Minimální přijatelný Current Ratio
	 */
	private const MIN_ACCEPTABLE_CURRENT_RATIO = 1.2;

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 7.0;

	private float|null $debtEquityRatio = null;

	private float|null $currentRatio = null;

	private float|null $debtAdjustment = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();

		$bookValuePerShare = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
		)?->getFloatValue();

		$debtEquityRatio = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
		)?->getFloatValue();

		$currentRatio = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::CURRENT_RATIO,
		)?->getFloatValue();

		$this->debtEquityRatio = $debtEquityRatio;
		$this->currentRatio = $currentRatio;

		if ($bookValuePerShare === null || $bookValuePerShare <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Základní fair value je 1.2x book value (mírně optimističtější)
		$baseFairValue = $bookValuePerShare * 1.2;

		// Výpočet debt adjustment faktoru
		$debtAdjustmentFactor = $this->calculateDebtAdjustment($debtEquityRatio, $currentRatio);
		$this->debtAdjustment = $debtAdjustmentFactor;

		// Aplikujeme debt adjustment na fair value
		$fairPrice = $baseFairValue * $debtAdjustmentFactor;

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

	/**
	 * Vypočítá adjustment faktor na základě zadlužení
	 *
	 * @return float Faktor mezi 0.7 (velmi špatné) a 1.3 (velmi dobré)
	 */
	private function calculateDebtAdjustment(float|null $debtEquity, float|null $currentRatio): float
	{
		$adjustmentFactor = 1.0; // Neutrální základ

		// Hodnocení Debt/Equity ratio (mírnější škála)
		if ($debtEquity !== null) {
			if ($debtEquity <= self::GOOD_DEBT_EQUITY_RATIO) {
				// Velmi nízké zadlužení = bonus +15%
				$adjustmentFactor += 0.15;
			} elseif ($debtEquity <= self::ACCEPTABLE_DEBT_EQUITY) {
				// Přijatelné zadlužení = malý bonus/penalizace (lineární škála)
				// 0.3-1.5: od +15% do 0%
				$ratio = ($debtEquity - self::GOOD_DEBT_EQUITY_RATIO) /
					(self::ACCEPTABLE_DEBT_EQUITY - self::GOOD_DEBT_EQUITY_RATIO);
				$adjustmentFactor += 0.15 * (1 - $ratio);
			} elseif ($debtEquity <= self::HIGH_DEBT_EQUITY) {
				// Zvýšené zadlužení = mírná penalizace
				// 1.5-3.0: od 0% do -15%
				$ratio = ($debtEquity - self::ACCEPTABLE_DEBT_EQUITY) /
					(self::HIGH_DEBT_EQUITY - self::ACCEPTABLE_DEBT_EQUITY);
				$adjustmentFactor -= 0.15 * $ratio;
			} else {
				// Extrémní zadlužení = výrazná penalizace -30%
				$adjustmentFactor -= 0.3;
			}
		}

		// Hodnocení likvidity (mírnější)
		if ($currentRatio !== null) {
			if ($currentRatio >= self::OPTIMAL_CURRENT_RATIO) {
				// Výborná likvidita = bonus +10%
				$adjustmentFactor += 0.1;
			} elseif ($currentRatio >= self::MIN_ACCEPTABLE_CURRENT_RATIO) {
				// Přijatelná likvidita = malý bonus
				// 1.2-2.0: od 0% do +10%
				$ratio = ($currentRatio - self::MIN_ACCEPTABLE_CURRENT_RATIO) /
					(self::OPTIMAL_CURRENT_RATIO - self::MIN_ACCEPTABLE_CURRENT_RATIO);
				$adjustmentFactor += 0.1 * $ratio;
			} else {
				// Špatná likvidita = penalizace -10%
				$adjustmentFactor -= 0.1;
			}
		}

		// Omezíme adjustment na rozumné rozmezí (mírnější než předtím)
		return max(0.7, min(1.3, $adjustmentFactor));
	}

	protected function getLabel(): string
	{
		return 'Debt-Adjusted Book Value';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
			StockValuationTypeEnum::CURRENT_RATIO,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('Base Multiplier', 1.2),
			new StockValuationModelUsedValue('GOOD_DEBT_EQUITY', self::GOOD_DEBT_EQUITY_RATIO),
			new StockValuationModelUsedValue('ACCEPTABLE_DEBT_EQUITY', self::ACCEPTABLE_DEBT_EQUITY),
			new StockValuationModelUsedValue('HIGH_DEBT_EQUITY', self::HIGH_DEBT_EQUITY),
		];

		if ($this->debtEquityRatio !== null) {
			$values[] = new StockValuationModelUsedValue('Current Debt/Equity', $this->debtEquityRatio);
		}

		if ($this->currentRatio !== null) {
			$values[] = new StockValuationModelUsedValue('Current Ratio', $this->currentRatio);
		}

		if ($this->debtAdjustment !== null) {
			$values[] = new StockValuationModelUsedValue('Debt Adjustment Factor', $this->debtAdjustment);
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
		return 'Upravuje účetní hodnotu na základě zadlužení a likvidity společnosti. Firmy s nízkým dluhem a dobrou likviditou získávají bonus, vysoce zadlužené jsou penalizovány.';
		//phpcs:enable
	}

}
