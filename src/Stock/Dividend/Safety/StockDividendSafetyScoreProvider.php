<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Safety;

use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendTypeEnum;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;

class StockDividendSafetyScoreProvider
{

	public function __construct(
		private StockValuationDataRepository $stockValuationDataRepository,
		private StockAssetDividendRepository $stockAssetDividendRepository,
	)
	{
	}

	public function getForStockAsset(StockAsset $stockAsset): StockDividendSafetyScore
	{
		$valuations = $this->stockValuationDataRepository->findTypesLatestForStockAsset($stockAsset, [
			StockValuationTypeEnum::PAYOUT_RATIO,
			StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW,
			StockValuationTypeEnum::TOTAL_DEBT_EQUITY,
			StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH,
		]);

		$dividends = $this->stockAssetDividendRepository->findByStockAsset($stockAsset);

		$score = 100;
		$reasons = [];

		$score += $this->scorePayoutRatio(
			$this->getValuationValue($valuations, StockValuationTypeEnum::PAYOUT_RATIO),
			$reasons,
		);
		$score += $this->scoreFreeCashFlow(
			$this->getValuationValue($valuations, StockValuationTypeEnum::LEVERED_FREE_CASH_FLOW),
			$reasons,
		);
		$score += $this->scoreDebtToEquity(
			$this->getValuationValue($valuations, StockValuationTypeEnum::TOTAL_DEBT_EQUITY),
			$reasons,
		);
		$score += $this->scoreEarningsGrowth(
			$this->getValuationValue($valuations, StockValuationTypeEnum::QUARTERLY_EARNINGS_GROWTH),
			$reasons,
		);
		$score += $this->scoreDividendHistory($dividends, $reasons);

		$score = max(0, min(100, $score));

		return new StockDividendSafetyScore(
			$score,
			$this->getStatus($score),
			$reasons,
		);
	}

	/**
	 * @param array<string, StockValuationData> $valuations
	 */
	private function getValuationValue(array $valuations, StockValuationTypeEnum $type): float|null
	{
		return isset($valuations[$type->value]) ? $valuations[$type->value]->getFloatValue() : null;
	}

	/** @param array<string, string> $reasons */
	private function scorePayoutRatio(float|null $payoutRatio, array &$reasons): int
	{
		if ($payoutRatio === null) {
			$reasons['Výplatní poměr'] = 'Chybí údaj o výplatním poměru';
			return -8;
		}

		if ($payoutRatio <= 0.0) {
			$reasons['Výplatní poměr'] = 'Výplatní poměr je záporný nebo nulový';
			return -25;
		}

		if ($payoutRatio <= 70.0) {
			$reasons['Výplatní poměr'] = sprintf('Udržitelný výplatní poměr %.1f %%', $payoutRatio);
			return 0;
		}

		if ($payoutRatio <= 90.0) {
			$reasons['Výplatní poměr'] = sprintf('Zvýšený výplatní poměr %.1f %%', $payoutRatio);
			return -18;
		}

		$reasons['Výplatní poměr'] = sprintf('Vysoký výplatní poměr %.1f %%', $payoutRatio);
		return -35;
	}

	/** @param array<string, string> $reasons */
	private function scoreFreeCashFlow(float|null $freeCashFlow, array &$reasons): int
	{
		if ($freeCashFlow === null) {
			$reasons['Volné cash flow'] = 'Chybí údaj o volném cash flow';
			return -5;
		}

		if ($freeCashFlow < 0.0) {
			$reasons['Volné cash flow'] = 'Volné cash flow je záporné';
			return -20;
		}

		$reasons['Volné cash flow'] = 'Volné cash flow je kladné';
		return 0;
	}

	/** @param array<string, string> $reasons */
	private function scoreDebtToEquity(float|null $debtToEquity, array &$reasons): int
	{
		if ($debtToEquity === null) {
			$reasons['Dluh / vlastní kapitál'] = 'Chybí údaj o zadlužení';
			return -5;
		}

		if ($debtToEquity <= 100.0) {
			$reasons['Dluh / vlastní kapitál'] = sprintf('Nízké zadlužení %.1f %%', $debtToEquity);
			return 0;
		}

		if ($debtToEquity <= 200.0) {
			$reasons['Dluh / vlastní kapitál'] = sprintf('Zvýšené zadlužení %.1f %%', $debtToEquity);
			return -12;
		}

		$reasons['Dluh / vlastní kapitál'] = sprintf('Vysoké zadlužení %.1f %%', $debtToEquity);
		return -22;
	}

	/** @param array<string, string> $reasons */
	private function scoreEarningsGrowth(float|null $earningsGrowth, array &$reasons): int
	{
		if ($earningsGrowth === null) {
			$reasons['Růst zisku'] = 'Chybí údaj o čtvrtletním růstu zisku';
			return -5;
		}

		if ($earningsGrowth < -20.0) {
			$reasons['Růst zisku'] = sprintf('Zisk klesá o %.1f %%', abs($earningsGrowth));
			return -18;
		}

		if ($earningsGrowth < 0.0) {
			$reasons['Růst zisku'] = sprintf('Zisk mírně klesá o %.1f %%', abs($earningsGrowth));
			return -8;
		}

		$reasons['Růst zisku'] = sprintf('Zisk roste o %.1f %%', $earningsGrowth);
		return 0;
	}

	/**
	 * @param array<StockAssetDividend> $dividends
	 * @param array<string, string> $reasons
	 */
	private function scoreDividendHistory(array $dividends, array &$reasons): int
	{
		$regularDividendsByYear = [];
		foreach ($dividends as $dividend) {
			if ($dividend->getDividendType() !== StockAssetDividendTypeEnum::REGULAR) {
				continue;
			}

			$year = $dividend->getExDate()->getYear();
			$regularDividendsByYear[$year] = ($regularDividendsByYear[$year] ?? 0.0) + $dividend->getAmount();
		}

		ksort($regularDividendsByYear);
		if (count($regularDividendsByYear) < 2) {
			$reasons['Historie dividend'] = 'Krátká historie dividend';
			return -8;
		}

		$cutsCount = 0;
		$previousAmount = null;
		foreach ($regularDividendsByYear as $amount) {
			if ($previousAmount !== null && $amount < $previousAmount * 0.95) {
				$cutsCount++;
			}

			$previousAmount = $amount;
		}

		if ($cutsCount === 0) {
			$reasons['Historie dividend'] = 'V dostupné historii nedošlo ke snížení dividendy';
			return 0;
		}

		$reasons['Historie dividend'] = sprintf('Počet snížení dividendy v dostupné historii: %d', $cutsCount);
		return min(0, -15 * $cutsCount);
	}

	private function getStatus(int $score): StockDividendSafetyScoreStatusEnum
	{
		if ($score >= 75) {
			return StockDividendSafetyScoreStatusEnum::SAFE;
		}

		if ($score >= 50) {
			return StockDividendSafetyScoreStatusEnum::WATCH;
		}

		return StockDividendSafetyScoreStatusEnum::RISKY;
	}

}
