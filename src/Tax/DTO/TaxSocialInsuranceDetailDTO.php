<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxSocialInsuranceDetailDTO
{

	public function __construct(
		public int $section7TaxBaseCzk,
		public int $activeMonths,
		public int $actualAssessmentBaseCzk,
		public int $minimumAssessmentBaseCzk,
		public int $maximumAssessmentBaseCzk,
		public int $usedAssessmentBaseCzk,
		public bool $usedMinimumBase,
		public bool $usedMaximumBase,
		public int $annualInsuranceCzk,
		public int $nextMonthlyAdvanceCzk,
		public int $nextYearMinimumMonthlyAdvanceCzk,
		public int $sicknessInsurancePaidCzk,
	)
	{
	}

}
