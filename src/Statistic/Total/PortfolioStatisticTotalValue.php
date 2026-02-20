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
	 * Celkový zisk od začátku portfolia
	 * Prostě: aktuální hodnota - celkem investováno
	 */
	public function getTotalProfit(): float
	{
		return $this->valueAtEnd - $this->investedAtEnd;
	}

	/**
	 * Zisk za konkrétní období (např. za rok)
	 * Odpovídá na otázku: "Kolik jsem vydělal v tomto období?"
	 * Bere v úvahu, že během období mohly přibýt nové investice
	 */
	public function getPeriodProfit(): float
	{
		$investedDiff = $this->investedAtEnd - $this->investedAtStart;
		$valueDiff = $this->valueAtEnd - $this->valueAtStart;
		return $valueDiff - $investedDiff;
	}

	/**
	 * Alias pro getPeriodProfit() - stejná logika
	 */
	public function getDiffAmount(): float
	{
		return $this->getPeriodProfit();
	}

	/**
	 * Celková procentuální výkonnost od začátku
	 * Počítá: (celkový zisk / celkem investováno) * 100
	 */
	public function getTotalPerformancePercentage(): float
	{
		if ($this->investedAtEnd === 0.0) {
			return 0;
		}

		return $this->getTotalProfit() / $this->investedAtEnd * 100;
	}

	/**
	 * Time-Weighted Return - výkonnost bez vlivu cash flow
	 * Nejpřesnější metrika pro porovnání výkonnosti
	 *
	 * Vzorec: ((valueAtEnd / (valueAtStart + newInvestments)) - 1) * 100
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
	 * Annualizovaný TWR - přepočítá TWR na roční bázi
	 * Zobrazuje se pouze pro periody > 180 dní (jinak dává nesmyslně vysoké hodnoty)
	 *
	 * Vzorec: ((1 + TWR/100) ^ (365/days)) - 1) * 100
	 */
	public function getAnnualizedTwr(): float|null
	{
		if ($this->startDate === null || $this->endDate === null) {
			return null;
		}

		$days = $this->getDaysInPeriod();

		// Annualizace dává smysl pouze pro periody delší než 180 dní
		if ($days <= 180) {
			return null;
		}

		// Pro přesně roční periodu (365/366 dní) annualizace = TWR
		if ($days === 365 || $days === 366) {
			return $this->getTimeWeightedReturn();
		}

		$twr = $this->getTimeWeightedReturn() / 100;

		return (((1 + $twr) ** (365 / $days)) - 1) * 100;
	}

	/**
	 * Money-Weighted Return (Modified Dietz method) - výnos zohledňující timing vkladů
	 * Odpovídá na otázku: "Kolik mi moje peníze s mým timingem vydělávají?"
	 *
	 * Modified Dietz: každý cash flow je vážen podle toho, jak dlouho byl v portfoliu
	 * MWR = (valueAtEnd - valueAtStart - ΣCF_i) / (valueAtStart + Σ(CF_i × W_i))
	 * kde W_i = (T - t_i) / T  (podíl zbývajícího období po vkladu)
	 *
	 * Pokud nejsou k dispozici detailní data, padne zpět na Simple Dietz.
	 * Pro roky (> 180 dní) vrací annualizovanou hodnotu.
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
			// Fallback: Simple Dietz — předpokládá cash flow v půlce období
			$denominator = $this->valueAtStart + ($totalCashFlow / 2);
		}

		if ($denominator <= 0.0) {
			return null;
		}

		// Čistý výnos za období
		$gain = $this->valueAtEnd - $this->valueAtStart - $totalCashFlow;

		$mwr = $gain / $denominator * 100;

		// Pro delší periody (> 180 dní) annualizovat
		if ($days > 180) {
			return (((1 + $mwr / 100) ** (365 / $days)) - 1) * 100;
		}

		return $mwr;
	}

	/**
	 * Vypočítá jmenovatel Modified Dietz: valueAtStart + Σ(ΔCF_i × W_i)
	 * kde ΔCF_i = změna investované částky v daný den
	 *     W_i   = (daysTotal - daysSinceStart) / daysTotal
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

	private function getDaysInPeriod(): int
	{
		if ($this->startDate === null || $this->endDate === null) {
			return 0;
		}

		$diff = $this->startDate->diff($this->endDate);

		return $diff->days !== false ? $diff->days : 0;
	}

}
