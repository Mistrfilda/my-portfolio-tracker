<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

class PortfolioPeriodStatisticSummaryDTO
{

	/**
	 * @param array<string> $warnings
	 */
	public function __construct(
		public float $investedAtStart,
		public float $investedAtEnd,
		public float $investedDifference,
		public float $valueAtStart,
		public float $valueAtEnd,
		public float $valueDifference,
		public float|null $valueDifferencePercentage,
		public float $periodProfit,
		public float $closedPositionsProfit,
		public float $netDividends,
		public float $totalPeriodProfit,
		public float $timeWeightedReturn,
		public float|null $annualizedTimeWeightedReturn,
		public float|null $moneyWeightedReturn,
		public float|null $xirr,
		public array $warnings = [],
		public bool $partial = false,
	)
	{
	}

}
