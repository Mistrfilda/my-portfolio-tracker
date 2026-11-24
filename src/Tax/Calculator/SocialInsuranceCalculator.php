<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxInstitutionSummaryDTO;
use App\Tax\DTO\TaxSocialInsuranceDetailDTO;
use App\Tax\Rules\TaxInstitutionEnum;
use App\Tax\Rules\TaxYearRules;

class SocialInsuranceCalculator
{

	/**
	 * @return array{detail: TaxSocialInsuranceDetailDTO, summary: TaxInstitutionSummaryDTO}
	 */
	public function calculate(
		int $section7TaxBaseCzk,
		int $activeMonths,
		TaxYearRules $yearRules,
		TaxYearRules $nextYearRules,
		int $paidAdvancesCzk,
		int $expectedRemainingAdvancesCzk,
		int $sicknessInsurancePaidCzk,
		string $advancesSourceNote,
	): array
	{
		$normalizedMonths = max(0, min(12, $activeMonths));
		$actualAssessmentBaseCzk = $section7TaxBaseCzk > 0
			? TaxMath::ceilCzk($section7TaxBaseCzk * $yearRules->socialAssessmentBaseRate)
			: 0;
		$minimumAssessmentBaseCzk = $normalizedMonths * $yearRules->socialMinMonthlyBaseForSettlementCzk;
		$maximumAssessmentBaseCzk = $yearRules->socialMaxAnnualBaseCzk;

		$usedMinimumBase = $minimumAssessmentBaseCzk > $actualAssessmentBaseCzk;
		$baseAfterMin = max($actualAssessmentBaseCzk, $minimumAssessmentBaseCzk);
		$usedMaximumBase = $baseAfterMin > $maximumAssessmentBaseCzk;
		$usedAssessmentBaseCzk = min($baseAfterMin, $maximumAssessmentBaseCzk);

		$annualInsuranceCzk = TaxMath::ceilCzk($usedAssessmentBaseCzk * $yearRules->socialInsuranceRate);

		$nextYearMinAdvance = $nextYearRules->socialMinMonthlyAdvanceH1Czk;
		$nextMonthlyAdvanceCzk = $nextYearMinAdvance;
		if ($normalizedMonths > 0) {
			$monthlyBaseRaw = TaxMath::ceilCzk($actualAssessmentBaseCzk / $normalizedMonths);
			$nextMaxMonthlyBase = intdiv($nextYearRules->socialMaxAnnualBaseCzk, 12);
			$cappedMonthlyBase = min($monthlyBaseRaw, $nextMaxMonthlyBase);
			$calculatedAdvance = TaxMath::ceilCzk($cappedMonthlyBase * $nextYearRules->socialInsuranceRate);
			$nextMonthlyAdvanceCzk = max($calculatedAdvance, $nextYearMinAdvance);
		}

		$totalCountedAdvancesCzk = $paidAdvancesCzk + $expectedRemainingAdvancesCzk;
		$settlementCzk = $annualInsuranceCzk - $totalCountedAdvancesCzk;

		$detail = new TaxSocialInsuranceDetailDTO(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			activeMonths: $normalizedMonths,
			actualAssessmentBaseCzk: $actualAssessmentBaseCzk,
			minimumAssessmentBaseCzk: $minimumAssessmentBaseCzk,
			maximumAssessmentBaseCzk: $maximumAssessmentBaseCzk,
			usedAssessmentBaseCzk: $usedAssessmentBaseCzk,
			usedMinimumBase: $usedMinimumBase,
			usedMaximumBase: $usedMaximumBase,
			annualInsuranceCzk: $annualInsuranceCzk,
			nextMonthlyAdvanceCzk: $nextMonthlyAdvanceCzk,
			nextYearMinimumMonthlyAdvanceCzk: $nextYearMinAdvance,
			sicknessInsurancePaidCzk: $sicknessInsurancePaidCzk,
		);

		$summary = new TaxInstitutionSummaryDTO(
			institutionCode: TaxInstitutionEnum::SOCIAL_INSURANCE->value,
			institutionLabel: TaxInstitutionEnum::SOCIAL_INSURANCE->formatShort(),
			annualLiabilityCzk: $annualInsuranceCzk,
			paidAdvancesCzk: $paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $expectedRemainingAdvancesCzk,
			totalCountedAdvancesCzk: $totalCountedAdvancesCzk,
			settlementCzk: $settlementCzk,
			payableShortfallCzk: max(0, $settlementCzk),
			expectedOverpaymentCzk: max(0, -$settlementCzk),
			nextAdvanceAmountCzk: $nextMonthlyAdvanceCzk,
			nextAdvancePeriodicity: 'měsíčně (v průběhu měsíce)',
			nextAdvanceEffectiveFrom: 'Od měsíce následujícího po podání přehledu',
			advancesSourceNote: $advancesSourceNote,
		);

		return [
			'detail' => $detail,
			'summary' => $summary,
		];
	}

}
