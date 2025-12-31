<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class DividendPayoutSafetyModel extends BasePriceModel
{

	/**
	 * Ideální payout ratio pro stabilní dividendové akcie
	 */
	private const IDEAL_PAYOUT_RATIO = 50.0;

	/**
	 * Bezpečná horní hranice payout ratio
	 */
	private const SAFE_PAYOUT_UPPER = 70.0;

	/**
	 * Bezpečná dolní hranice (příliš nízké = nevyužitý potenciál)
	 */
	private const SAFE_PAYOUT_LOWER = 25.0;

	/**
	 * Kritická hranice - nebezpečně vysoké
	 */
	private const CRITICAL_PAYOUT = 90.0;

	private const UNDERPRICED_THRESHOLD = 10.0;

	private const OVERPRICED_THRESHOLD = -10.0;

	private const FAIR_VALUE_THRESHOLD = 5.0;

	private float|null $currentPayoutRatio = null;

	private float|null $safetyMultiplier = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockAsset->getAssetCurrentPrice()->getPrice();

		$payoutRatio = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::PAYOUT_RATIO,
		)?->getFloatValue();

		$dilutedEps = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::DILUTED_EPS,
		)?->getFloatValue();

		$forwardDividendRate = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
		)?->getFloatValue();

		$this->currentPayoutRatio = $payoutRatio;

		if (
			$stockAsset->doesPaysDividends() === false
			|| $payoutRatio === null
			|| $dilutedEps === null
			|| $dilutedEps <= 0
			|| $forwardDividendRate === null
			|| $forwardDividendRate <= 0
		) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Vypočítáme safety multiplier na základě payout ratio
		$safetyMultiplier = $this->calculateSafetyMultiplier($payoutRatio);
		$this->safetyMultiplier = $safetyMultiplier;

		// Základní P/E pro dividendové akcie je 15
		$basePE = 15.0;

		// Upravíme P/E podle bezpečnosti dividendy
		$adjustedPE = $basePE * $safetyMultiplier;

		$fairPrice = $dilutedEps * $adjustedPE;

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
	 * Vypočítá bezpečnostní multiplikátor na základě payout ratio
	 *
	 * - 25-50%: bonus (prostor pro růst dividendy)
	 * - 50-70%: ideální zóna
	 * - 70-90%: penalizace (málo prostoru)
	 * - >90%: výrazná penalizace (neudržitelné)
	 */
	private function calculateSafetyMultiplier(float $payoutRatio): float
	{
		if ($payoutRatio <= 0) {
			return 0.5; // Negativní nebo nulové = velká penalizace
		}

		if ($payoutRatio < self::SAFE_PAYOUT_LOWER) {
			// Příliš nízké payout - mírně neutrální
			return 0.95;
		}

		if ($payoutRatio <= self::IDEAL_PAYOUT_RATIO) {
			// 25-50%: bonus za prostor k růstu
			$ratio = ($payoutRatio - self::SAFE_PAYOUT_LOWER)
				/ (self::IDEAL_PAYOUT_RATIO - self::SAFE_PAYOUT_LOWER);
			return 1.0 + ($ratio * 0.15); // 1.0 až 1.15
		}

		if ($payoutRatio <= self::SAFE_PAYOUT_UPPER) {
			// 50-70%: ideální zóna, mírný pokles
			$ratio = ($payoutRatio - self::IDEAL_PAYOUT_RATIO)
				/ (self::SAFE_PAYOUT_UPPER - self::IDEAL_PAYOUT_RATIO);
			return 1.15 - ($ratio * 0.15); // 1.15 až 1.0
		}

		if ($payoutRatio <= self::CRITICAL_PAYOUT) {
			// 70-90%: penalizace
			$ratio = ($payoutRatio - self::SAFE_PAYOUT_UPPER)
				/ (self::CRITICAL_PAYOUT - self::SAFE_PAYOUT_UPPER);
			return 1.0 - ($ratio * 0.25); // 1.0 až 0.75
		}

		// >90%: výrazná penalizace
		return max(0.5, 0.75 - (($payoutRatio - self::CRITICAL_PAYOUT) / 100));
	}

	protected function getLabel(): string
	{
		return 'Dividend Payout Safety';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::PAYOUT_RATIO,
			StockValuationTypeEnum::DILUTED_EPS,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('IDEAL_PAYOUT_RATIO', self::IDEAL_PAYOUT_RATIO),
			new StockValuationModelUsedValue('SAFE_PAYOUT_UPPER', self::SAFE_PAYOUT_UPPER),
			new StockValuationModelUsedValue('CRITICAL_PAYOUT', self::CRITICAL_PAYOUT),
		];

		if ($this->currentPayoutRatio !== null) {
			$values[] = new StockValuationModelUsedValue('Current Payout Ratio (%)', $this->currentPayoutRatio);
		}

		if ($this->safetyMultiplier !== null) {
			$values[] = new StockValuationModelUsedValue('Safety Multiplier', $this->safetyMultiplier);
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
		return 'Hodnotí udržitelnost dividendy na základě payout ratio. Ideální je rozmezí 25-70 %, příliš vysoké payout ratio signalizuje riziko snížení dividendy.';
		//phpcs:enable
	}

}
