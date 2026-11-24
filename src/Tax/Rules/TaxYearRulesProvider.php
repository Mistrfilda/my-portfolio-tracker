<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

class TaxYearRulesProvider
{

	public function getForYear(int $year): TaxYearRules
	{
		return match ($year) {
			2025 => new TaxYearRules(
				year: 2025,
				averageWageCzk: 46_557,
				higherTaxBracketThresholdCzk: 1_676_052,
				basicTaxRate: 0.15,
				higherTaxRate: 0.23,
				basicTaxpayerCreditCzk: 30_840,
				giftMinCzk: 1_000,
				giftMinBasePercentage: 0.02,
				giftMaxBasePercentage: 0.30,
				healthInsuranceRate: 0.135,
				healthAssessmentBaseRate: 0.50,
				healthMinMonthlyBaseCzk: 23_278.5,
				healthMinMonthlyAdvanceCzk: 3_143,
				socialInsuranceRate: 0.292,
				socialAssessmentBaseRate: 0.55,
				socialMinMonthlyBaseForSettlementCzk: 16_295,
				socialMinMonthlyAdvanceH1Czk: 4_759,
				socialMinMonthlyAdvanceH2Czk: 4_759,
				socialMaxAnnualBaseCzk: 2_234_736,
				stockAnnualProceedsExemptionLimitCzk: 100_000,
				stockTimeTestYears: 3,
				stockTimeExemptionAnnualCapCzk: 40_000_000,
				exemptIncomeNotificationThresholdCzk: 5_000_000,
				incomeTaxAdvanceLowerThresholdCzk: 30_000,
				incomeTaxAdvanceUpperThresholdCzk: 150_000,
				isEstimated: false,
			),
			2026 => new TaxYearRules(
				year: 2026,
				averageWageCzk: 48_967,
				higherTaxBracketThresholdCzk: 1_762_812,
				basicTaxRate: 0.15,
				higherTaxRate: 0.23,
				basicTaxpayerCreditCzk: 30_840,
				giftMinCzk: 1_000,
				giftMinBasePercentage: 0.02,
				giftMaxBasePercentage: 0.30,
				healthInsuranceRate: 0.135,
				healthAssessmentBaseRate: 0.50,
				healthMinMonthlyBaseCzk: 24_483.5,
				healthMinMonthlyAdvanceCzk: 3_306,
				socialInsuranceRate: 0.292,
				socialAssessmentBaseRate: 0.55,
				socialMinMonthlyBaseForSettlementCzk: 17_139,
				socialMinMonthlyAdvanceH1Czk: 5_720,
				socialMinMonthlyAdvanceH2Czk: 5_005,
				socialMaxAnnualBaseCzk: 2_350_416,
				stockAnnualProceedsExemptionLimitCzk: 100_000,
				stockTimeTestYears: 3,
				stockTimeExemptionAnnualCapCzk: null,
				exemptIncomeNotificationThresholdCzk: 5_000_000,
				incomeTaxAdvanceLowerThresholdCzk: 30_000,
				incomeTaxAdvanceUpperThresholdCzk: 150_000,
				isEstimated: false,
			),
			2027 => new TaxYearRules(
				year: 2027,
				averageWageCzk: 51_663,
				higherTaxBracketThresholdCzk: 1_859_868,
				basicTaxRate: 0.15,
				higherTaxRate: 0.23,
				basicTaxpayerCreditCzk: 30_840,
				giftMinCzk: 1_000,
				giftMinBasePercentage: 0.02,
				giftMaxBasePercentage: 0.15,
				healthInsuranceRate: 0.135,
				healthAssessmentBaseRate: 0.50,
				healthMinMonthlyBaseCzk: 25_831.5,
				healthMinMonthlyAdvanceCzk: 3_488,
				socialInsuranceRate: 0.292,
				socialAssessmentBaseRate: 0.55,
				socialMinMonthlyBaseForSettlementCzk: 18_083,
				socialMinMonthlyAdvanceH1Czk: 5_281,
				socialMinMonthlyAdvanceH2Czk: 5_281,
				socialMaxAnnualBaseCzk: 2_479_824,
				stockAnnualProceedsExemptionLimitCzk: 100_000,
				stockTimeTestYears: 3,
				stockTimeExemptionAnnualCapCzk: null,
				exemptIncomeNotificationThresholdCzk: 5_000_000,
				incomeTaxAdvanceLowerThresholdCzk: 30_000,
				incomeTaxAdvanceUpperThresholdCzk: 150_000,
				isEstimated: false,
			),
			default => $this->buildFallbackRules($year),
		};
	}

	/**
	 * @return list<int>
	 */
	public function getSupportedYears(): array
	{
		return [2025, 2026, 2027];
	}

	private function buildFallbackRules(int $year): TaxYearRules
	{
		$base = $year < 2025 ? $this->getForYear(2025) : $this->getForYear(2027);

		return new TaxYearRules(
			year: $year,
			averageWageCzk: $base->averageWageCzk,
			higherTaxBracketThresholdCzk: $base->higherTaxBracketThresholdCzk,
			basicTaxRate: $base->basicTaxRate,
			higherTaxRate: $base->higherTaxRate,
			basicTaxpayerCreditCzk: $base->basicTaxpayerCreditCzk,
			giftMinCzk: $base->giftMinCzk,
			giftMinBasePercentage: $base->giftMinBasePercentage,
			giftMaxBasePercentage: $base->giftMaxBasePercentage,
			healthInsuranceRate: $base->healthInsuranceRate,
			healthAssessmentBaseRate: $base->healthAssessmentBaseRate,
			healthMinMonthlyBaseCzk: $base->healthMinMonthlyBaseCzk,
			healthMinMonthlyAdvanceCzk: $base->healthMinMonthlyAdvanceCzk,
			socialInsuranceRate: $base->socialInsuranceRate,
			socialAssessmentBaseRate: $base->socialAssessmentBaseRate,
			socialMinMonthlyBaseForSettlementCzk: $base->socialMinMonthlyBaseForSettlementCzk,
			socialMinMonthlyAdvanceH1Czk: $base->socialMinMonthlyAdvanceH1Czk,
			socialMinMonthlyAdvanceH2Czk: $base->socialMinMonthlyAdvanceH2Czk,
			socialMaxAnnualBaseCzk: $base->socialMaxAnnualBaseCzk,
			stockAnnualProceedsExemptionLimitCzk: $base->stockAnnualProceedsExemptionLimitCzk,
			stockTimeTestYears: $base->stockTimeTestYears,
			stockTimeExemptionAnnualCapCzk: $year <= 2025 ? 40_000_000 : null,
			exemptIncomeNotificationThresholdCzk: $base->exemptIncomeNotificationThresholdCzk,
			incomeTaxAdvanceLowerThresholdCzk: $base->incomeTaxAdvanceLowerThresholdCzk,
			incomeTaxAdvanceUpperThresholdCzk: $base->incomeTaxAdvanceUpperThresholdCzk,
			isEstimated: true,
		);
	}

}
