<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

use InvalidArgumentException;

final class FinancialIndependenceCalculator
{

	public const int MAX_PROJECTION_YEARS = 100;

	private const int MONTHS_PER_YEAR = 12;

	public function calculate(
		FinancialIndependenceCalculationInput $input,
		int|null $projectionHorizonMonths = null,
	): FinancialIndependenceCalculationResult
	{
		$this->validateInput($input);
		$this->validateProjectionHorizon($projectionHorizonMonths);

		$withdrawalRate = $input->withdrawalRatePercentage / 100;
		$targetPortfolioValueCzk = $input->targetMonthlyExpensesCzk
			* self::MONTHS_PER_YEAR
			/ $withdrawalRate;
		if (!is_finite($targetPortfolioValueCzk)) {
			throw new InvalidArgumentException('Target portfolio value must be finite.');
		}

		$annualGrowthFactor = (1 + ($input->annualNominalReturnPercentage / 100))
			* (1 - ($input->annualCostPercentage / 100))
			/ (1 + ($input->annualInflationPercentage / 100));
		$netRealAnnualReturn = $annualGrowthFactor - 1;
		$effectiveMonthlyReturn = ($annualGrowthFactor ** (1 / self::MONTHS_PER_YEAR)) - 1;
		$currentMonthlyWithdrawalCzk = $input->currentPortfolioValueCzk
			* $withdrawalRate
			/ self::MONTHS_PER_YEAR;
		$portfolioGapCzk = max(0.0, $targetPortfolioValueCzk - $input->currentPortfolioValueCzk);
		$fundedPercentage = $targetPortfolioValueCzk === 0.0
			? 100.0
			: min(100.0, $input->currentPortfolioValueCzk / $targetPortfolioValueCzk * 100);

		$projectionPoints = [
			new FinancialIndependenceProjectionPoint(0, $input->currentPortfolioValueCzk),
		];
		$monthsToTarget = $input->currentPortfolioValueCzk >= $targetPortfolioValueCzk ? 0 : null;
		$portfolioValueCzk = $input->currentPortfolioValueCzk;

		if ($monthsToTarget === null) {
			$technicalProjectionHorizonMonths = self::MAX_PROJECTION_YEARS * self::MONTHS_PER_YEAR;
			$maxProjectionMonths = min(
				$technicalProjectionHorizonMonths,
				$projectionHorizonMonths ?? $technicalProjectionHorizonMonths,
			);
			for ($month = 1; $month <= $maxProjectionMonths; $month++) {
				$portfolioValueCzk = $portfolioValueCzk * (1 + $effectiveMonthlyReturn)
					+ $input->monthlyContributionCzk;
				$projectionPoints[] = new FinancialIndependenceProjectionPoint($month, $portfolioValueCzk);

				if ($portfolioValueCzk >= $targetPortfolioValueCzk) {
					$monthsToTarget = $month;
					break;
				}
			}
		}

		return new FinancialIndependenceCalculationResult(
			$targetPortfolioValueCzk,
			$currentMonthlyWithdrawalCzk,
			$portfolioGapCzk,
			$fundedPercentage,
			$netRealAnnualReturn * 100,
			$effectiveMonthlyReturn * 100,
			$monthsToTarget,
			$projectionPoints,
		);
	}

	private function validateInput(FinancialIndependenceCalculationInput $input): void
	{
		$this->assertFiniteNonNegative($input->currentPortfolioValueCzk, 'Current portfolio value');
		$this->assertFiniteNonNegative($input->targetMonthlyExpensesCzk, 'Target monthly expenses');
		$this->assertFiniteNonNegative($input->monthlyContributionCzk, 'Monthly contribution');

		if (!is_finite($input->annualNominalReturnPercentage)
			|| $input->annualNominalReturnPercentage < -100
		) {
			throw new InvalidArgumentException(
				'Annual nominal return must be a finite percentage greater than or equal to -100.',
			);
		}

		if (!is_finite($input->annualInflationPercentage)
			|| $input->annualInflationPercentage <= -100
		) {
			throw new InvalidArgumentException(
				'Annual inflation must be a finite percentage greater than -100.',
			);
		}

		if (!is_finite($input->annualCostPercentage)
			|| $input->annualCostPercentage < 0
			|| $input->annualCostPercentage > 100
		) {
			throw new InvalidArgumentException(
				'Annual cost must be a finite percentage between 0 and 100.',
			);
		}

		if (!is_finite($input->withdrawalRatePercentage)
			|| $input->withdrawalRatePercentage <= 0
			|| $input->withdrawalRatePercentage > 100
		) {
			throw new InvalidArgumentException(
				'Withdrawal rate must be a finite percentage greater than 0 and less than or equal to 100.',
			);
		}

		if ($input->birthYear <= 0) {
			throw new InvalidArgumentException('Birth year must be greater than zero.');
		}

		if ($input->plannedLifespanAge < 1 || $input->plannedLifespanAge > 150) {
			throw new InvalidArgumentException('Planned lifespan age must be between 1 and 150.');
		}
	}

	private function validateProjectionHorizon(int|null $projectionHorizonMonths): void
	{
		if ($projectionHorizonMonths !== null && $projectionHorizonMonths < 0) {
			throw new InvalidArgumentException('Projection horizon must be greater than or equal to zero months.');
		}
	}

	private function assertFiniteNonNegative(float $value, string $name): void
	{
		if (!is_finite($value) || $value < 0) {
			throw new InvalidArgumentException(
				sprintf('%s must be a finite number greater than or equal to zero.', $name),
			);
		}
	}

}
