<?php

declare(strict_types = 1);

namespace App\Tax\Rules;

final readonly class TaxYearRules
{

	public function __construct(
		public int $year,
		public int $averageWageCzk,
		public int $higherTaxBracketThresholdCzk,
		public float $basicTaxRate,
		public float $higherTaxRate,
		public int $basicTaxpayerCreditCzk,
		public int $giftMinCzk,
		public float $giftMinBasePercentage,
		public float $giftMaxBasePercentage,
		public float $healthInsuranceRate,
		public float $healthAssessmentBaseRate,
		public float $healthMinMonthlyBaseCzk,
		public int $healthMinMonthlyAdvanceCzk,
		public float $socialInsuranceRate,
		public float $socialAssessmentBaseRate,
		public int $socialMinMonthlyBaseForSettlementCzk,
		public int $socialMinMonthlyAdvanceH1Czk,
		public int $socialMinMonthlyAdvanceH2Czk,
		public int $socialMaxAnnualBaseCzk,
		public int $stockAnnualProceedsExemptionLimitCzk,
		public int $stockTimeTestYears,
		public int|null $stockTimeExemptionAnnualCapCzk,
		public int $exemptIncomeNotificationThresholdCzk,
		public int $incomeTaxAdvanceLowerThresholdCzk,
		public int $incomeTaxAdvanceUpperThresholdCzk,
		public bool $isEstimated,
	)
	{
	}

	public function getSocialMinMonthlyAdvanceForMonth(int $month): int
	{
		if ($month >= 7) {
			return $this->socialMinMonthlyAdvanceH2Czk;
		}

		return $this->socialMinMonthlyAdvanceH1Czk;
	}

	public function getSocialMinAnnualAdvanceSum(): int
	{
		return (6 * $this->socialMinMonthlyAdvanceH1Czk) + (6 * $this->socialMinMonthlyAdvanceH2Czk);
	}

	public function getHealthMinAnnualAdvanceSum(): int
	{
		return 12 * $this->healthMinMonthlyAdvanceCzk;
	}

}
