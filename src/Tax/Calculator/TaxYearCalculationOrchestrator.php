<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxScenarioComparisonDTO;
use App\Tax\DTO\TaxYearReportSnapshotDTO;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxExpenseFlatRateEnum;
use App\Tax\Rules\TaxYearRulesProvider;

class TaxYearCalculationOrchestrator
{

	public function __construct(
		private TaxYearRulesProvider $rulesProvider,
		private BusinessIncomeCalculator $businessIncomeCalculator,
		private HealthInsuranceCalculator $healthInsuranceCalculator,
		private SocialInsuranceCalculator $socialInsuranceCalculator,
		private IncomeTaxCalculator $incomeTaxCalculator,
	)
	{
	}

	/**
	 * Synchronously recalculates all three institutions for an alternative annual § 7 business income
	 * directly on top of a persisted report snapshot without hitting the queue.
	 */
	public function recalculateScenario(
		TaxYearReportSnapshotDTO $baseline,
		int $customAnnualBusinessIncomeCzk,
	): TaxScenarioComparisonDTO
	{
		$normalizedIncome = max(0, $customAnnualBusinessIncomeCzk);
		$flatRate = TaxExpenseFlatRateEnum::from($baseline->businessIncome->flatRateCode);
		$dividendMode = TaxDividendModeEnum::from($baseline->dividends->selectedMode);
		$yearRules = $this->rulesProvider->getForYear($baseline->year);
		$nextYearRules = $this->rulesProvider->getForYear($baseline->year + 1);

		$expensesCzk = $this->businessIncomeCalculator->calculateFlatRateExpenses($normalizedIncome, $flatRate);
		$section7TaxBaseCzk = $this->businessIncomeCalculator->calculateTaxBase($normalizedIncome, $flatRate);

		$health = $this->healthInsuranceCalculator->calculate(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			activeMonths: $baseline->healthInsuranceDetail->activeMonths,
			yearRules: $yearRules,
			nextYearRules: $nextYearRules,
			paidAdvancesCzk: $baseline->healthInsuranceSummary->paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $baseline->healthInsuranceSummary->expectedRemainingAdvancesCzk,
			advancesSourceNote: $baseline->healthInsuranceSummary->advancesSourceNote,
		);

		$social = $this->socialInsuranceCalculator->calculate(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			activeMonths: $baseline->socialInsuranceDetail->activeMonths,
			yearRules: $yearRules,
			nextYearRules: $nextYearRules,
			paidAdvancesCzk: $baseline->socialInsuranceSummary->paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $baseline->socialInsuranceSummary->expectedRemainingAdvancesCzk,
			sicknessInsurancePaidCzk: $baseline->socialInsuranceDetail->sicknessInsurancePaidCzk,
			advancesSourceNote: $baseline->socialInsuranceSummary->advancesSourceNote,
		);

		$incomeTax = $this->incomeTaxCalculator->calculate(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			section10TaxBaseCzk: $baseline->stockSales->taxBaseSection10Czk,
			dividends: $baseline->dividends,
			selectedDividendMode: $dividendMode,
			rawDonationsCzk: $baseline->incomeTaxDetail->rawDonationsCzk,
			pensionAndLifeDeductionCzk: $baseline->incomeTaxDetail->pensionAndLifeDeductionCzk,
			mortgageInterestDeductionCzk: $baseline->incomeTaxDetail->mortgageInterestDeductionCzk,
			otherDeductionsCzk: $baseline->incomeTaxDetail->otherDeductionsCzk,
			otherTaxCreditsCzk: $baseline->incomeTaxDetail->otherTaxCreditsCzk,
			rules: $yearRules,
			paidAdvancesCzk: $baseline->taxOfficeSummary->paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $baseline->taxOfficeSummary->expectedRemainingAdvancesCzk,
			advancesSourceNote: $baseline->taxOfficeSummary->advancesSourceNote,
		);

		$totalAnnualLiabilityCzk = $incomeTax['summary']->annualLiabilityCzk
			+ $health['summary']->annualLiabilityCzk
			+ $social['summary']->annualLiabilityCzk;

		$totalReserveToPrepareCzk = $incomeTax['summary']->payableShortfallCzk
			+ $health['summary']->payableShortfallCzk
			+ $social['summary']->payableShortfallCzk;

		$totalExpectedOverpaymentsCzk = $incomeTax['summary']->expectedOverpaymentCzk
			+ $health['summary']->expectedOverpaymentCzk
			+ $social['summary']->expectedOverpaymentCzk;

		return new TaxScenarioComparisonDTO(
			annualBusinessIncomeCzk: $normalizedIncome,
			section7ExpensesCzk: $expensesCzk,
			section7TaxBaseCzk: $section7TaxBaseCzk,
			taxOfficeSummary: $incomeTax['summary'],
			healthInsuranceSummary: $health['summary'],
			socialInsuranceSummary: $social['summary'],
			totalAnnualLiabilityCzk: $totalAnnualLiabilityCzk,
			totalReserveToPrepareCzk: $totalReserveToPrepareCzk,
			totalExpectedOverpaymentsCzk: $totalExpectedOverpaymentsCzk,
			reserveDifferenceFromBaselineCzk: $totalReserveToPrepareCzk - $baseline->totalReserveToPrepareCzk,
			isBelowActualReceivedIncome: $normalizedIncome < $baseline->businessIncome->actualReceivedIncomeCzk,
			actualReceivedIncomeCzk: $baseline->businessIncome->actualReceivedIncomeCzk,
		);
	}

}
