<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxBusinessIncomeSectionDTO;
use App\Tax\DTO\TaxWorkIncomeMonthItemDTO;
use App\Tax\Rules\TaxExpenseFlatRateEnum;

class BusinessIncomeCalculator
{

	/**
	 * @param list<TaxWorkIncomeMonthItemDTO> $items
	 */
	public function calculate(
		array $items,
		TaxExpenseFlatRateEnum $flatRate,
		int $manualAdjustmentCzk,
		string|null $manualAdjustmentNote,
		string $autoForecastPeriodDescription,
		int $autoForecastMonthsUsed,
		int|null $customForecastAnnualIncomeCzk,
		bool $useCustomForecast,
		bool $isOpenYear,
	): TaxBusinessIncomeSectionDTO
	{
		$actualReceivedIncomeCzk = 0;
		$estimatedRemainingIncomeCzk = 0;

		foreach ($items as $item) {
			if ($item->isEstimated) {
				$estimatedRemainingIncomeCzk += $item->amountCzk;
			} else {
				$actualReceivedIncomeCzk += $item->amountCzk;
			}
		}

		$autoForecastAnnualIncomeCzk = max(
			0,
			$actualReceivedIncomeCzk + $estimatedRemainingIncomeCzk + $manualAdjustmentCzk,
		);

		$usedAnnualIncomeCzk = $isOpenYear && $useCustomForecast && $customForecastAnnualIncomeCzk !== null
			? max(0, $customForecastAnnualIncomeCzk)
			: $autoForecastAnnualIncomeCzk;

		$expensesCzk = $this->calculateFlatRateExpenses($usedAnnualIncomeCzk, $flatRate);
		$taxBaseCzk = max(0, $usedAnnualIncomeCzk - $expensesCzk);

		return new TaxBusinessIncomeSectionDTO(
			actualReceivedIncomeCzk: $actualReceivedIncomeCzk,
			estimatedRemainingIncomeCzk: $estimatedRemainingIncomeCzk,
			manualAdjustmentCzk: $manualAdjustmentCzk,
			manualAdjustmentNote: $manualAdjustmentNote,
			autoForecastAnnualIncomeCzk: $autoForecastAnnualIncomeCzk,
			autoForecastPeriodDescription: $autoForecastPeriodDescription,
			autoForecastMonthsUsed: $autoForecastMonthsUsed,
			customForecastAnnualIncomeCzk: $customForecastAnnualIncomeCzk,
			useCustomForecast: $useCustomForecast && $isOpenYear,
			usedAnnualIncomeCzk: $usedAnnualIncomeCzk,
			flatRateCode: $flatRate->value,
			flatRatePercentage: $flatRate->getPercentage(),
			maxExpensesCzk: $flatRate->getMaxExpensesCzk(),
			expensesCzk: $expensesCzk,
			taxBaseCzk: $taxBaseCzk,
			items: $items,
		);
	}

	public function calculateFlatRateExpenses(int $annualIncomeCzk, TaxExpenseFlatRateEnum $flatRate): int
	{
		if ($annualIncomeCzk <= 0) {
			return 0;
		}

		$rawExpenses = TaxMath::roundCzk($annualIncomeCzk * $flatRate->getPercentage() / 100);

		return min($rawExpenses, $flatRate->getMaxExpensesCzk());
	}

	public function calculateTaxBase(int $annualIncomeCzk, TaxExpenseFlatRateEnum $flatRate): int
	{
		if ($annualIncomeCzk <= 0) {
			return 0;
		}

		return max(0, $annualIncomeCzk - $this->calculateFlatRateExpenses($annualIncomeCzk, $flatRate));
	}

}
