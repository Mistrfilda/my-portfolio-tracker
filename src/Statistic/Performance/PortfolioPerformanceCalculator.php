<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Statistic\Total\XirrCalculator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceCalculator
{

	private const float ROUNDING_TOLERANCE = 1.0;

	public function calculateMonth(
		ImmutableDateTime $periodStartAt,
		ImmutableDateTime $periodEndAt,
		float $investedAtStart,
		float $investedAtEnd,
		float $valueAtStart,
		float $valueAtEnd,
		float $realizedProfit,
		float $netDividends,
		float $cashAtStart,
		ImmutableDateTime $now,
	): PortfolioPerformanceMonth
	{
		$fundingNeed = $investedAtEnd - $investedAtStart - $realizedProfit - $netDividends;
		if (abs($fundingNeed) < self::ROUNDING_TOLERANCE) {
			$fundingNeed = 0.0;
		}

		if ($fundingNeed > $cashAtStart) {
			$externalContribution = $fundingNeed - $cashAtStart;
			$cashAtEnd = 0.0;
		} else {
			$externalContribution = 0.0;
			$cashAtEnd = $cashAtStart - $fundingNeed;
		}

		$accountValueAtStart = $valueAtStart + $cashAtStart;
		$accountValueAtEnd = $valueAtEnd + $cashAtEnd;
		$denominator = $accountValueAtStart + ($externalContribution / 2);
		if ($denominator <= 0.0) {
			throw new PortfolioPerformanceUnableToCalculateException(
				'Portfolio performance denominator must be positive.',
			);
		}

		$returnRate = (
			$accountValueAtEnd
			- $accountValueAtStart
			- $externalContribution
		) / $denominator;

		return new PortfolioPerformanceMonth(
			new ImmutableDateTime($periodEndAt->format('Y-m-01')),
			$periodStartAt,
			$periodEndAt,
			$investedAtStart,
			$investedAtEnd,
			$valueAtStart,
			$valueAtEnd,
			$realizedProfit,
			$netDividends,
			$cashAtStart,
			$cashAtEnd,
			$externalContribution,
			1 + $returnRate,
			$now,
		);
	}

	/**
	 * @param array<PortfolioPerformanceMonth> $months
	 */
	public function calculateSummary(array $months): PortfolioPerformanceSummary|null
	{
		if ($months === []) {
			return null;
		}

		$firstMonth = $months[0];
		$lastMonth = $months[count($months) - 1];
		$growthFactor = 1.0;
		foreach ($months as $month) {
			$growthFactor *= $month->getReturnFactor();
		}

		$timeWeightedReturn = ($growthFactor - 1) * 100;
		$days = $this->getDaysBetween($firstMonth->getPeriodStartAt(), $lastMonth->getPeriodEndAt());

		return new PortfolioPerformanceSummary(
			$firstMonth->getPeriodStartAt(),
			$lastMonth->getPeriodEndAt(),
			$timeWeightedReturn,
			$this->annualizeTimeWeightedReturn($timeWeightedReturn, $days),
			$this->calculateMoneyWeightedReturn($months, $days),
			$this->calculateXirr($months),
		);
	}

	private function annualizeTimeWeightedReturn(float $timeWeightedReturn, int $days): float|null
	{
		if ($days <= 180) {
			return null;
		}

		if ($days === 365 || $days === 366) {
			return $timeWeightedReturn;
		}

		return (((1 + ($timeWeightedReturn / 100)) ** (365 / $days)) - 1) * 100;
	}

	/**
	 * @param array<PortfolioPerformanceMonth> $months
	 */
	private function calculateMoneyWeightedReturn(array $months, int $days): float|null
	{
		if ($days <= 0) {
			return null;
		}

		$firstMonth = $months[0];
		$lastMonth = $months[count($months) - 1];
		$startDate = $firstMonth->getPeriodStartAt();
		$denominator = $firstMonth->getAccountValueAtStart();
		$totalCashFlow = 0.0;

		foreach ($months as $month) {
			$cashFlow = $month->getExternalContribution();
			if ($cashFlow === 0.0) {
				continue;
			}

			$daysSinceStart = $this->getDaysBetween($startDate, $month->getMidpoint());
			$weight = ($days - $daysSinceStart) / $days;
			$denominator += $cashFlow * $weight;
			$totalCashFlow += $cashFlow;
		}

		if ($denominator <= 0.0) {
			return null;
		}

		$return = (
			$lastMonth->getAccountValueAtEnd()
			- $firstMonth->getAccountValueAtStart()
			- $totalCashFlow
		) / $denominator * 100;

		if ($days > 180) {
			return (((1 + ($return / 100)) ** (365 / $days)) - 1) * 100;
		}

		return $return;
	}

	/**
	 * @param array<PortfolioPerformanceMonth> $months
	 */
	private function calculateXirr(array $months): float|null
	{
		$firstMonth = $months[0];
		$lastMonth = $months[count($months) - 1];
		$cashFlows = [
			[
				'date' => $firstMonth->getPeriodStartAt(),
				'amount' => -$firstMonth->getAccountValueAtStart(),
			],
		];

		foreach ($months as $month) {
			if ($month->getExternalContribution() === 0.0) {
				continue;
			}

			$cashFlows[] = [
				'date' => $month->getMidpoint(),
				'amount' => -$month->getExternalContribution(),
			];
		}

		$cashFlows[] = [
			'date' => $lastMonth->getPeriodEndAt(),
			'amount' => $lastMonth->getAccountValueAtEnd(),
		];

		$xirr = XirrCalculator::calculate($cashFlows);
		return $xirr !== null ? $xirr * 100 : null;
	}

	private function getDaysBetween(ImmutableDateTime $start, ImmutableDateTime $end): int
	{
		$days = $start->diff($end)->days;
		return $days !== false ? $days : 0;
	}

}
