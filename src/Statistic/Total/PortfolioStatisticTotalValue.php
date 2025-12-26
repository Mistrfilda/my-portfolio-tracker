<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

class PortfolioStatisticTotalValue
{

	public function __construct(
		private int|null $month,
		private string $label,
		private float $investedAtStart,
		private float $investedAtEnd,
		private float $valueAtStart,
		private float $valueAtEnd,
		private float $closedPositionsProfitInPeriod = 0.0,
		private float $dividendsInPeriod = 0.0,
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

}
