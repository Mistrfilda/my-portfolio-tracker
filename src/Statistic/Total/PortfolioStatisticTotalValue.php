<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioStatisticTotalValue
{

	/**
	 * @param array<array{date: ImmutableDateTime, amount: float}>|null $cashFlowData
	 */
	public function __construct(
		private int|null $month,
		private string $label,
		private float $investedAtStart,
		private float $investedAtEnd,
		private float $valueAtStart,
		private float $valueAtEnd,
		private float $closedPositionsProfitInPeriod = 0.0,
		private float $dividendsInPeriod = 0.0,
		private ImmutableDateTime|null $startDate = null,
		private ImmutableDateTime|null $endDate = null,
		private array|null $cashFlowData = null,
	)
	{
	}

	public function getMonth(): int|null
	{
		return $this->month;
	}

	public function getInvestedAtStart(): float
	{
		return $this->investedAtStart;
	}

	public function getInvestedAtEnd(): float
	{
		return $this->investedAtEnd;
	}

	public function getValueAtStart(): float
	{
		return $this->valueAtStart;
	}

	public function getValueAtEnd(): float
	{
		return $this->valueAtEnd;
	}

	public function getLabel(): string
	{
		return $this->label;
	}

	public function getClosedPositionsProfitInPeriod(): float
	{
		return $this->closedPositionsProfitInPeriod;
	}

	public function getDividendsInPeriod(): float
	{
		return $this->dividendsInPeriod;
	}

	/**
	 * Total profit since the beginning of the portfolio.
	 * Simply: current value - total invested.
	 */
	public function getTotalProfit(): float
	{
		return $this->valueAtEnd - $this->investedAtEnd;
	}

	/**
	 * Profit for a specific period (e.g. a year).
	 * Answers the question: "How much did I earn in this period?"
	 * Takes into account that new investments may have been added during the period.
	 */
	public function getPeriodProfit(): float
	{
		$investedDiff = $this->investedAtEnd - $this->investedAtStart;
		$valueDiff = $this->valueAtEnd - $this->valueAtStart;
		return $valueDiff - $investedDiff;
	}

	/**
	 * Alias for getPeriodProfit() - same logic.
	 */
	public function getDiffAmount(): float
	{
		return $this->getPeriodProfit();
	}

	/**
	 * Total percentage performance since the beginning.
	 * Formula: (total profit / total invested) * 100
	 */
	public function getTotalPerformancePercentage(): float
	{
		if ($this->investedAtEnd === 0.0) {
			return 0;
		}

		return $this->getTotalProfit() / $this->investedAtEnd * 100;
	}

	/**
	 * Time-Weighted Return - performance excluding the impact of cash flows.
	 * Most accurate metric for comparing performance.
	 *
	 * Formula: ((valueAtEnd / (valueAtStart + newInvestments)) - 1) * 100
	 */
	public function getTimeWeightedReturn(): float
	{
		$newInvestments = $this->investedAtEnd - $this->investedAtStart;
		$startingCapital = $this->valueAtStart + $newInvestments;

		if ($startingCapital === 0.0) {
			return 0;
		}

		return (($this->valueAtEnd / $startingCapital) - 1) * 100;
	}

	/**
	 * Annualized TWR - converts TWR to an annual basis.
	 * Displayed only for periods > 180 days (otherwise produces unreasonably high values).
	 *
	 * Formula: ((1 + TWR/100) ^ (365/days)) - 1) * 100
	 */
	public function getAnnualizedTwr(): float|null
	{
		if ($this->startDate === null || $this->endDate === null) {
			return null;
		}

		$days = $this->getDaysInPeriod();

		// Annualization only makes sense for periods longer than 180 days
		if ($days <= 180) {
			return null;
		}

		// For exactly one-year period (365/366 days) annualization = TWR
		if ($days === 365 || $days === 366) {
			return $this->getTimeWeightedReturn();
		}

		$twr = $this->getTimeWeightedReturn() / 100;

		return (((1 + $twr) ** (365 / $days)) - 1) * 100;
	}

	/**
	 * Money-Weighted Return (Modified Dietz method) - return accounting for the timing of deposits.
	 * Answers the question: "How much are my money earning given my timing?"
	 *
	 * Modified Dietz: each cash flow is weighted by how long it was in the portfolio.
	 * MWR = (valueAtEnd - valueAtStart - ΣCF_i) / (valueAtStart + Σ(CF_i × W_i))
	 * where W_i = (T - t_i) / T  (fraction of remaining period after deposit)
	 *
	 * Falls back to Simple Dietz if detailed data is not available.
	 * Returns annualized value for periods > 180 days.
	 */
	public function getMoneyWeightedReturn(): float|null
	{
		if ($this->startDate === null || $this->endDate === null) {
			return null;
		}

		$days = $this->getDaysInPeriod();
		if ($days <= 0) {
			return null;
		}

		$totalCashFlow = $this->investedAtEnd - $this->investedAtStart;

		if ($this->cashFlowData !== null && count($this->cashFlowData) >= 2) {
			$denominator = $this->computeModifiedDietzDenominator($days);
		} else {
			// Fallback: Simple Dietz — assumes cash flow at mid-period
			$denominator = $this->valueAtStart + ($totalCashFlow / 2);
		}

		if ($denominator <= 0.0) {
			return null;
		}

		// Net gain for the period
		$gain = $this->valueAtEnd - $this->valueAtStart - $totalCashFlow;

		$mwr = $gain / $denominator * 100;

		// Annualize for longer periods (> 180 days)
		if ($days > 180) {
			return (((1 + $mwr / 100) ** (365 / $days)) - 1) * 100;
		}

		return $mwr;
	}

	/**
	 * Computes the Modified Dietz denominator: valueAtStart + Σ(ΔCF_i × W_i)
	 * where ΔCF_i = change in invested amount on a given day
	 *       W_i   = (daysTotal - daysSinceStart) / daysTotal
	 */
	private function computeModifiedDietzDenominator(int $totalDays): float
	{
		assert($this->startDate !== null);
		assert($this->cashFlowData !== null);

		$denominator = $this->valueAtStart;
		$prevAmount = null;

		foreach ($this->cashFlowData as $entry) {
			if ($prevAmount === null) {
				$prevAmount = $entry['amount'];
				continue;
			}

			$cashFlowDelta = $entry['amount'] - $prevAmount;
			if ($cashFlowDelta === 0.0) {
				$prevAmount = $entry['amount'];
				continue;
			}

			$daysSinceStart = $this->startDate->diff($entry['date'])->days;
			$weight = ($totalDays - ($daysSinceStart !== false ? $daysSinceStart : 0)) / $totalDays;

			$denominator += $cashFlowDelta * $weight;
			$prevAmount = $entry['amount'];
		}

		return $denominator;
	}

	public function getTotalProfitWithClosedPositions(): float
	{
		return $this->getTotalProfit() + $this->getClosedPositionsProfitInPeriod();
	}

	public function getTotalProfitWithDividends(): float
	{
		return $this->getTotalProfit() + $this->getDividendsInPeriod();
	}

	public function getPeriodProfitWithClosedPositions(): float
	{
		return $this->getPeriodProfit() + $this->getClosedPositionsProfitInPeriod();
	}

	public function getPeriodProfitWithDividends(): float
	{
		return $this->getPeriodProfit() + $this->getDividendsInPeriod();
	}

	public function getPeriodProfitWithClosedPositionsAndDividends(): float
	{
		return $this->getPeriodProfit() + $this->getClosedPositionsProfitInPeriod() + $this->getDividendsInPeriod();
	}

	/**
	 * XIRR (Money-Weighted Return) - exact return accounting for timing and size of deposits.
	 * Returns annualized XIRR adjusted for the given period using the formula:
	 * (1 + xirr) ^ (days / 365) - 1
	 *
	 * For "all time" periods, XIRR is returned directly.
	 * For shorter periods, XIRR is adjusted to the period return.
	 *
	 * Cash flows are built from cashFlowData (investment deltas as negative values)
	 * and the final record (valueAtEnd as a positive value).
	 */
	public function getXirr(): float|null
	{
		if ($this->startDate === null || $this->endDate === null) {
			return null;
		}

		if ($this->cashFlowData === null || count($this->cashFlowData) < 1) {
			return null;
		}

		$xirrCashFlows = $this->buildXirrCashFlows();
		if ($xirrCashFlows === null) {
			return null;
		}

		$annualizedXirr = XirrCalculator::calculate($xirrCashFlows);
		if ($annualizedXirr === null) {
			return null;
		}

		$days = $this->getDaysInPeriod();

		// Adjust for period: (1 + xirr) ^ (days / 365) - 1
		$periodReturn = XirrCalculator::adjustForPeriod($annualizedXirr, $days);

		return $periodReturn * 100;
	}

	/**
	 * Builds the cash flow array for XIRR calculation.
	 * First entry = negative market value of portfolio at start of period (valueAtStart).
	 * Each change in invested amount during the period = negative cash flow (new investment).
	 * Last entry = positive market value of portfolio at end of period (valueAtEnd).
	 *
	 * @return array<array{date: ImmutableDateTime, amount: float}>|null
	 */
	private function buildXirrCashFlows(): array|null
	{
		if ($this->cashFlowData === null || count($this->cashFlowData) < 1) {
			return null;
		}

		if ($this->startDate === null || $this->endDate === null) {
			return null;
		}

		$cashFlows = [];

		// Initial cash flow = negative market value of portfolio at start of period
		// (as if we "bought" the portfolio at its current market value)
		if ($this->valueAtStart > 0) {
			$cashFlows[] = [
				'date' => $this->startDate,
				'amount' => -$this->valueAtStart,
			];
		}

		// Add new investments during the period as negative cash flows (invested amount deltas)
		$prevAmount = null;
		foreach ($this->cashFlowData as $entry) {
			if ($prevAmount === null) {
				$prevAmount = $entry['amount'];
				continue;
			}

			$delta = $entry['amount'] - $prevAmount;
			if (abs($delta) > 0.01) {
				$cashFlows[] = [
					'date' => $entry['date'],
					'amount' => -$delta,
				];
			}

			$prevAmount = $entry['amount'];
		}

		// Final cash flow = positive market value of portfolio at end of period
		// (as if we "sold" the portfolio at its current market value)
		if ($this->valueAtEnd > 0) {
			$cashFlows[] = [
				'date' => $this->endDate,
				'amount' => $this->valueAtEnd,
			];
		}

		if (count($cashFlows) < 2) {
			return null;
		}

		return $cashFlows;
	}

	private function getDaysInPeriod(): int
	{
		if ($this->startDate === null || $this->endDate === null) {
			return 0;
		}

		$diff = $this->startDate->diff($this->endDate);

		return $diff->days !== false ? $diff->days : 0;
	}

}
