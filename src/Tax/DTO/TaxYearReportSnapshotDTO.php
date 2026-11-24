<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxYearReportSnapshotDTO
{

	/**
	 * @param list<TaxDonationItemDTO> $donations
	 * @param list<TaxPaymentItemDTO> $payments
	 * @param list<string> $warnings
	 */
	public function __construct(
		public int $year,
		public string $asOfDate,
		public bool $isOpenYear,
		public bool $isClosed,
		public bool $isEstimatedRules,
		public string|null $reserveTargetDate,
		public TaxBusinessIncomeSectionDTO $businessIncome,
		public TaxStockSalesSectionDTO $stockSales,
		public TaxDividendsSectionDTO $dividends,
		public TaxHealthInsuranceDetailDTO $healthInsuranceDetail,
		public TaxSocialInsuranceDetailDTO $socialInsuranceDetail,
		public TaxIncomeTaxDetailDTO $incomeTaxDetail,
		public TaxInstitutionSummaryDTO $taxOfficeSummary,
		public TaxInstitutionSummaryDTO $healthInsuranceSummary,
		public TaxInstitutionSummaryDTO $socialInsuranceSummary,
		public int $totalAnnualLiabilityCzk,
		public int $totalPaidAdvancesCzk,
		public int $totalExpectedRemainingAdvancesCzk,
		public int $totalReserveToPrepareCzk,
		public int $totalExpectedOverpaymentsCzk,
		public int $totalRemainingCashflowThisYearAndSettlementCzk,
		public array $donations,
		public array $payments,
		public array $warnings,
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
