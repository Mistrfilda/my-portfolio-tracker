<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxHealthInsuranceDetailDTO;
use App\Tax\DTO\TaxInstitutionSummaryDTO;
use App\Tax\Rules\TaxInstitutionEnum;
use App\Tax\Rules\TaxYearRules;

class HealthInsuranceCalculator
{

	/**
	 * @return array{detail: TaxHealthInsuranceDetailDTO, summary: TaxInstitutionSummaryDTO}
	 */
	public function calculate(
		int $section7TaxBaseCzk,
		int $activeMonths,
		TaxYearRules $yearRules,
		TaxYearRules $nextYearRules,
		int $paidAdvancesCzk,
		int $expectedRemainingAdvancesCzk,
		string $advancesSourceNote,
	): array
	{
		$normalizedMonths = max(0, min(12, $activeMonths));
		$actualAssessmentBaseCzk = max(0.0, $section7TaxBaseCzk * $yearRules->healthAssessmentBaseRate);
		$minimumAssessmentBaseCzk = $normalizedMonths * $yearRules->healthMinMonthlyBaseCzk;
		$usedMinimumBase = $minimumAssessmentBaseCzk > $actualAssessmentBaseCzk;
		$usedAssessmentBaseCzk = max($actualAssessmentBaseCzk, $minimumAssessmentBaseCzk);

		$annualInsuranceCzk = TaxMath::ceilCzk($usedAssessmentBaseCzk * $yearRules->healthInsuranceRate);

		$nextMonthlyAdvanceCzk = $nextYearRules->healthMinMonthlyAdvanceCzk;
		if ($normalizedMonths > 0) {
			$averageMonthlyBase = $section7TaxBaseCzk * $yearRules->healthAssessmentBaseRate / $normalizedMonths;
			$calculatedAdvance = TaxMath::ceilCzk(max(0.0, $averageMonthlyBase) * $yearRules->healthInsuranceRate);
			$nextMonthlyAdvanceCzk = max($calculatedAdvance, $nextYearRules->healthMinMonthlyAdvanceCzk);
		}

		$totalCountedAdvancesCzk = $paidAdvancesCzk + $expectedRemainingAdvancesCzk;
		$settlementCzk = $annualInsuranceCzk - $totalCountedAdvancesCzk;

		$detail = new TaxHealthInsuranceDetailDTO(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			activeMonths: $normalizedMonths,
			actualAssessmentBaseCzk: round($actualAssessmentBaseCzk, 2),
			minimumAssessmentBaseCzk: round($minimumAssessmentBaseCzk, 2),
			usedAssessmentBaseCzk: round($usedAssessmentBaseCzk, 2),
			usedMinimumBase: $usedMinimumBase,
			annualInsuranceCzk: $annualInsuranceCzk,
			nextMonthlyAdvanceCzk: $nextMonthlyAdvanceCzk,
			nextYearMinimumMonthlyAdvanceCzk: $nextYearRules->healthMinMonthlyAdvanceCzk,
		);

		$summary = new TaxInstitutionSummaryDTO(
			institutionCode: TaxInstitutionEnum::HEALTH_INSURANCE->value,
			institutionLabel: TaxInstitutionEnum::HEALTH_INSURANCE->formatShort(),
			annualLiabilityCzk: $annualInsuranceCzk,
			paidAdvancesCzk: $paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $expectedRemainingAdvancesCzk,
			totalCountedAdvancesCzk: $totalCountedAdvancesCzk,
			settlementCzk: $settlementCzk,
			payableShortfallCzk: max(0, $settlementCzk),
			expectedOverpaymentCzk: max(0, -$settlementCzk),
			nextAdvanceAmountCzk: $nextMonthlyAdvanceCzk,
			nextAdvancePeriodicity: 'měsíčně (splatnost do 8. dne násl. měsíce)',
			nextAdvanceEffectiveFrom: 'Od měsíce podání přehledu',
			advancesSourceNote: $advancesSourceNote,
		);

		return [
			'detail' => $detail,
			'summary' => $summary,
		];
	}

}
