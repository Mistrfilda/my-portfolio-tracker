<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

final readonly class FinancialIndependenceCalculationInput
{

	public function __construct(
		public float $currentPortfolioValueCzk,
		public float $targetMonthlyExpensesCzk,
		public float $monthlyContributionCzk,
		public float $annualNominalReturnPercentage,
		public float $annualInflationPercentage,
		public float $annualCostPercentage,
		public float $withdrawalRatePercentage,
		public int $birthYear,
		public int $plannedLifespanAge,
	)
	{
	}

}
