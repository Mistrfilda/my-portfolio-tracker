<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\Price;

use App\Asset\Price\AssetPrice;
use App\Stock\Valuation\Model\StockValuationModelState;
use App\Stock\Valuation\Model\StockValuationModelUsedValue;
use App\Stock\Valuation\StockValuation;
use App\Stock\Valuation\StockValuationTypeEnum;

class RoeQualityValuationModel extends BasePriceModel
{

	/**
	 * Výborné ROE (nad 20%)
	 */
	private const EXCELLENT_ROE = 20.0;

	/**
	 * Dobré ROE (nad 15%)
	 */
	private const GOOD_ROE = 15.0;

	/**
	 * Přijatelné ROE (nad 10%)
	 */
	private const ACCEPTABLE_ROE = 10.0;

	/**
	 * Základní P/B multiplikátor
	 */
	private const BASE_PB_MULTIPLIER = 1.0;

	private const UNDERPRICED_THRESHOLD = 12.0;

	private const OVERPRICED_THRESHOLD = -12.0;

	private const FAIR_VALUE_THRESHOLD = 7.0;

	private float|null $roe = null;

	private float|null $adjustedPbMultiplier = null;

	public function calculateResponse(StockValuation $stockValuation): StockValuationPriceModelResponse
	{
		$stockAsset = $stockValuation->getStockAsset();
		$currentPrice = $stockValuation->getStockAsset()->getAssetCurrentPrice()->getPrice();

		$bookValuePerShare = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
		)?->getFloatValue();

		$roe = $stockValuation->getValuationDataByType(
			StockValuationTypeEnum::RETURN_ON_EQUITY,
		)?->getFloatValue();

		$this->roe = $roe;

		if ($bookValuePerShare === null || $bookValuePerShare <= 0) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		if ($roe === null) {
			return $this->getUnableToCalculateResponse($stockValuation);
		}

		// Výpočet P/B multiplikátoru na základě ROE
		// Společnosti s vysokým ROE si zaslouží vyšší P/B ratio
		$pbMultiplier = $this->calculatePbMultiplier($roe);
		$this->adjustedPbMultiplier = $pbMultiplier;

		// Fair value = Book Value × ROE-adjusted P/B multiplier
		$fairPrice = $bookValuePerShare * $pbMultiplier;

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
	 * Vypočítá P/B multiplikátor na základě ROE
	 *
	 * Logika:
	 * - ROE > 20%: P/B 2.5-3.0 (excelentní společnost)
	 * - ROE 15-20%: P/B 2.0-2.5 (velmi dobrá společnost)
	 * - ROE 10-15%: P/B 1.5-2.0 (dobrá společnost)
	 * - ROE 5-10%: P/B 1.0-1.5 (průměrná společnost)
	 * - ROE < 5%: P/B 0.8-1.0 (slabá společnost)
	 * - ROE < 0%: P/B 0.5-0.8 (ztrátová společnost)
	 */
	private function calculatePbMultiplier(float $roe): float
	{
		if ($roe >= self::EXCELLENT_ROE) {
			// ROE > 20%: lineární škála od 2.5 do 3.0
			// Čím vyšší ROE, tím vyšší multiplikátor (max 3.0 při ROE 30%)
			$excess = min($roe - self::EXCELLENT_ROE, 10.0); // Max bonus za 10% nad 20%
			return 2.5 + ($excess / 10.0) * 0.5;
		}

		if ($roe >= self::GOOD_ROE) {
			// ROE 15-20%: lineární škála od 2.0 do 2.5
			$ratio = ($roe - self::GOOD_ROE) / (self::EXCELLENT_ROE - self::GOOD_ROE);
			return 2.0 + ($ratio * 0.5);
		}

		if ($roe >= self::ACCEPTABLE_ROE) {
			// ROE 10-15%: lineární škála od 1.5 do 2.0
			$ratio = ($roe - self::ACCEPTABLE_ROE) / (self::GOOD_ROE - self::ACCEPTABLE_ROE);
			return 1.5 + ($ratio * 0.5);
		}

		if ($roe >= 5.0) {
			// ROE 5-10%: lineární škála od 1.0 do 1.5
			$ratio = ($roe - 5.0) / (self::ACCEPTABLE_ROE - 5.0);
			return 1.0 + ($ratio * 0.5);
		}

		if ($roe >= 0) {
			// ROE 0-5%: lineární škála od 0.8 do 1.0
			$ratio = $roe / 5.0;
			return 0.8 + ($ratio * 0.2);
		}

		// ROE < 0%: penalizace za ztrátu
		// ROE -10% = 0.5, ROE 0% = 0.8
		$negativeRatio = max($roe, -10.0) / -10.0; // 0 až 1
		return 0.8 - ($negativeRatio * 0.3);
	}

	protected function getLabel(): string
	{
		return 'ROE Quality Model';
	}

	/**
	 * @return array<StockValuationTypeEnum>
	 */
	protected function getUsedTypes(): array
	{
		return [
			StockValuationTypeEnum::CURRENT_PRICE,
			StockValuationTypeEnum::BOOK_VALUE_PER_SHARE,
			StockValuationTypeEnum::RETURN_ON_EQUITY,
		];
	}

	/**
	 * @return array<StockValuationModelUsedValue>
	 */
	protected function getModelUsedValues(): array
	{
		$values = [
			new StockValuationModelUsedValue('BASE_PB_MULTIPLIER', self::BASE_PB_MULTIPLIER),
			new StockValuationModelUsedValue('EXCELLENT_ROE', self::EXCELLENT_ROE),
			new StockValuationModelUsedValue('GOOD_ROE', self::GOOD_ROE),
			new StockValuationModelUsedValue('ACCEPTABLE_ROE', self::ACCEPTABLE_ROE),
		];

		if ($this->roe !== null) {
			$values[] = new StockValuationModelUsedValue('Current ROE (%)', $this->roe);
		}

		if ($this->adjustedPbMultiplier !== null) {
			$values[] = new StockValuationModelUsedValue('Adjusted P/B Multiplier', $this->adjustedPbMultiplier);
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
		return 'Upravuje P/B multiplikátor podle rentability vlastního kapitálu (ROE). Firmy s vysokým ROE si zaslouží vyšší ocenění než průměrné společnosti.';
		//phpcs:enable
	}

}
