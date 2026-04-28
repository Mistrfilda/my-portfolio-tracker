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
			$reasons['Payout ratio'] = 'Missing payout ratio';
			return -8;
		}

		if ($payoutRatio <= 0.0) {
			$reasons['Payout ratio'] = 'Negative or zero payout ratio';
			return -25;
		}

		if ($payoutRatio <= 70.0) {
			$reasons['Payout ratio'] = sprintf('Healthy payout ratio %.1f %%', $payoutRatio);
			return 0;
		}

		if ($payoutRatio <= 90.0) {
			$reasons['Payout ratio'] = sprintf('Elevated payout ratio %.1f %%', $payoutRatio);
			return -18;
		}

		$reasons['Payout ratio'] = sprintf('High payout ratio %.1f %%', $payoutRatio);
		return -35;
	}

	/** @param array<string, string> $reasons */
	private function scoreFreeCashFlow(float|null $freeCashFlow, array &$reasons): int
	{
		if ($freeCashFlow === null) {
			$reasons['Free cash flow'] = 'Missing free cash flow';
			return -5;
		}

		if ($freeCashFlow < 0.0) {
			$reasons['Free cash flow'] = 'Negative free cash flow';
			return -20;
		}

		$reasons['Free cash flow'] = 'Positive free cash flow';
		return 0;
	}

	/** @param array<string, string> $reasons */
	private function scoreDebtToEquity(float|null $debtToEquity, array &$reasons): int
	{
		if ($debtToEquity === null) {
			$reasons['Debt to equity'] = 'Missing debt to equity';
			return -5;
		}

		if ($debtToEquity <= 100.0) {
			$reasons['Debt to equity'] = sprintf('Low debt to equity %.1f %%', $debtToEquity);
			return 0;
		}

		if ($debtToEquity <= 200.0) {
			$reasons['Debt to equity'] = sprintf('Elevated debt to equity %.1f %%', $debtToEquity);
			return -12;
		}

		$reasons['Debt to equity'] = sprintf('High debt to equity %.1f %%', $debtToEquity);
		return -22;
	}

	/** @param array<string, string> $reasons */
	private function scoreEarningsGrowth(float|null $earningsGrowth, array &$reasons): int
	{
		if ($earningsGrowth === null) {
			$reasons['Earnings growth'] = 'Missing quarterly earnings growth';
			return -5;
		}

		if ($earningsGrowth < -20.0) {
			$reasons['Earnings growth'] = sprintf('Earnings are falling %.1f %%', $earningsGrowth);
			return -18;
		}

		if ($earningsGrowth < 0.0) {
			$reasons['Earnings growth'] = sprintf('Earnings are slightly falling %.1f %%', $earningsGrowth);
			return -8;
		}

		$reasons['Earnings growth'] = sprintf('Positive earnings growth %.1f %%', $earningsGrowth);
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
			$reasons['Dividend history'] = 'Short dividend history';
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
			$reasons['Dividend history'] = 'No dividend cut in available history';
			return 0;
		}

		$reasons['Dividend history'] = sprintf('%d dividend cut(s) in available history', $cutsCount);
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
