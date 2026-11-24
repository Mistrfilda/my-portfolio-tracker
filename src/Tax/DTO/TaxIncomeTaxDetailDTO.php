<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxIncomeTaxDetailDTO
{

	public function __construct(
		public int $section7TaxBaseCzk,
		public int $section8TaxBaseCzk,
		public int $section10TaxBaseCzk,
		public int $totalBaseBeforeDeductionsCzk,
		public int $rawDonationsCzk,
		public int $deductibleDonationsCzk,
		public bool $donationsBelowMinimum,
		public bool $donationsCappedAtMax,
		public int $pensionAndLifeDeductionCzk,
		public int $mortgageInterestDeductionCzk,
		public int $otherDeductionsCzk,
		public int $totalDeductionsCzk,
		public int $roundedTaxBaseCzk,
		public int $higherTaxBracketThresholdCzk,
		public int $baseIn15BracketCzk,
		public int $baseIn23BracketCzk,
		public int $taxIn15BracketCzk,
		public int $taxIn23BracketCzk,
		public int $standardTaxBeforeCreditCzk,
		public int $foreignTaxCreditCzk,
		public int $basicTaxpayerCreditCzk,
		public int $otherTaxCreditsCzk,
		public int $standardTaxAfterCreditsCzk,
		public int $separateBase16aTaxAfterCreditCzk,
		public int $totalAnnualTaxCzk,
		public int $alternativeModeTotalAnnualTaxCzk,
		public string $alternativeModeCode,
		public int $advanceBaseTaxWithoutSection10And16aCzk,
		public int $nextAdvanceAmountCzk,
		public string $nextAdvancePeriodicity,
		public int $nextAdvanceCountPerYear,
	)
	{
	}

}
