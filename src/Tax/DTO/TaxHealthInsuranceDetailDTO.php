<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxHealthInsuranceDetailDTO
{

	public function __construct(
		public int $section7TaxBaseCzk,
		public int $activeMonths,
		public float $actualAssessmentBaseCzk,
		public float $minimumAssessmentBaseCzk,
		public float $usedAssessmentBaseCzk,
		public bool $usedMinimumBase,
		public int $annualInsuranceCzk,
		public int $nextMonthlyAdvanceCzk,
		public int $nextYearMinimumMonthlyAdvanceCzk,
	)
	{
	}

}
