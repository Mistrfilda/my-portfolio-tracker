<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxScenarioComparisonDTO
{

	public function __construct(
		public int $annualBusinessIncomeCzk,
		public int $section7ExpensesCzk,
		public int $section7TaxBaseCzk,
		public TaxInstitutionSummaryDTO $taxOfficeSummary,
		public TaxInstitutionSummaryDTO $healthInsuranceSummary,
		public TaxInstitutionSummaryDTO $socialInsuranceSummary,
		public int $totalAnnualLiabilityCzk,
		public int $totalReserveToPrepareCzk,
		public int $totalExpectedOverpaymentsCzk,
		public int $reserveDifferenceFromBaselineCzk,
		public bool $isBelowActualReceivedIncome,
		public int $actualReceivedIncomeCzk,
	)
	{
	}

	/**
	 * @return list<TaxInstitutionSummaryDTO>
	 */
	public function getInstitutionSummaries(): array
	{
		return [
			$this->taxOfficeSummary,
			$this->healthInsuranceSummary,
			$this->socialInsuranceSummary,
		];
	}

}
