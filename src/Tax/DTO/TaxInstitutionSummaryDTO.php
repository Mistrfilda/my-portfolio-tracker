<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxInstitutionSummaryDTO
{

	public function __construct(
		public string $institutionCode,
		public string $institutionLabel,
		public int $annualLiabilityCzk,
		public int $paidAdvancesCzk,
		public int $expectedRemainingAdvancesCzk,
		public int $totalCountedAdvancesCzk,
		public int $settlementCzk,
		public int $payableShortfallCzk,
		public int $expectedOverpaymentCzk,
		public int $nextAdvanceAmountCzk,
		public string $nextAdvancePeriodicity,
		public string $nextAdvanceEffectiveFrom,
		public string $advancesSourceNote,
	)
	{
	}

}
