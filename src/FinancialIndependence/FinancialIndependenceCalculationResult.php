<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

final readonly class FinancialIndependenceCalculationResult
{

	/**
	 * @param list<FinancialIndependenceProjectionPoint> $projectionPoints
	 */
	public function __construct(
		public float $targetPortfolioValueCzk,
		public float $currentMonthlyWithdrawalCzk,
		public float $portfolioGapCzk,
		public float $fundedPercentage,
		public float $netRealAnnualReturnPercentage,
		public float $effectiveMonthlyReturnPercentage,
		public int|null $monthsToTarget,
		public array $projectionPoints,
	)
	{
	}

}
