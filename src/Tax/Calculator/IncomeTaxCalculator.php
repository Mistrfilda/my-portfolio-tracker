<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxDividendsSectionDTO;
use App\Tax\DTO\TaxIncomeTaxDetailDTO;
use App\Tax\DTO\TaxInstitutionSummaryDTO;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxInstitutionEnum;
use App\Tax\Rules\TaxYearRules;

class IncomeTaxCalculator
{

	public function __construct(
		private DividendTaxCalculator $dividendTaxCalculator,
	)
	{
	}

	/**
	 * @return array{
	 *     detail: TaxIncomeTaxDetailDTO,
	 *     summary: TaxInstitutionSummaryDTO,
	 *     dividends: TaxDividendsSectionDTO
	 * }
	 */
	public function calculate(
		int $section7TaxBaseCzk,
		int $section10TaxBaseCzk,
		TaxDividendsSectionDTO $dividends,
		TaxDividendModeEnum $selectedDividendMode,
		int $rawDonationsCzk,
		int $pensionAndLifeDeductionCzk,
		int $mortgageInterestDeductionCzk,
		int $otherDeductionsCzk,
		int $otherTaxCreditsCzk,
		TaxYearRules $rules,
		int $paidAdvancesCzk,
		int $expectedRemainingAdvancesCzk,
		string $advancesSourceNote,
	): array
	{
		$standardBranch = $this->calculateBranch(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			section8TaxBaseCzk: $dividends->foreignGrossCzk,
			section10TaxBaseCzk: $section10TaxBaseCzk,
			dividends: $dividends,
			mode: TaxDividendModeEnum::STANDARD_SECTION_8,
			rawDonationsCzk: $rawDonationsCzk,
			pensionAndLifeDeductionCzk: $pensionAndLifeDeductionCzk,
			mortgageInterestDeductionCzk: $mortgageInterestDeductionCzk,
			otherDeductionsCzk: $otherDeductionsCzk,
			otherTaxCreditsCzk: $otherTaxCreditsCzk,
			rules: $rules,
		);

		$separateBranch = $this->calculateBranch(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			section8TaxBaseCzk: 0,
			section10TaxBaseCzk: $section10TaxBaseCzk,
			dividends: $dividends,
			mode: TaxDividendModeEnum::SEPARATE_BASE_16A,
			rawDonationsCzk: $rawDonationsCzk,
			pensionAndLifeDeductionCzk: $pensionAndLifeDeductionCzk,
			mortgageInterestDeductionCzk: $mortgageInterestDeductionCzk,
			otherDeductionsCzk: $otherDeductionsCzk,
			otherTaxCreditsCzk: $otherTaxCreditsCzk,
			rules: $rules,
		);

		$chosen = $selectedDividendMode === TaxDividendModeEnum::STANDARD_SECTION_8
			? $standardBranch
			: $separateBranch;
		$alternative = $selectedDividendMode === TaxDividendModeEnum::STANDARD_SECTION_8
			? $separateBranch
			: $standardBranch;
		$alternativeMode = $selectedDividendMode === TaxDividendModeEnum::STANDARD_SECTION_8
			? TaxDividendModeEnum::SEPARATE_BASE_16A
			: TaxDividendModeEnum::STANDARD_SECTION_8;

		// § 38a: last known tax liability excluding § 10 and § 16a separate base
		$advanceBranch = $this->calculateBranch(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			section8TaxBaseCzk: $selectedDividendMode === TaxDividendModeEnum::STANDARD_SECTION_8
				? $dividends->foreignGrossCzk
				: 0,
			section10TaxBaseCzk: 0,
			dividends: $dividends,
			mode: $selectedDividendMode,
			rawDonationsCzk: $rawDonationsCzk,
			pensionAndLifeDeductionCzk: $pensionAndLifeDeductionCzk,
			mortgageInterestDeductionCzk: $mortgageInterestDeductionCzk,
			otherDeductionsCzk: $otherDeductionsCzk,
			otherTaxCreditsCzk: $otherTaxCreditsCzk,
			rules: $rules,
			includeSeparate16aTax: false,
		);

		$advanceBaseTax = $advanceBranch['totalAnnualTaxCzk'];
		$nextAdvance = $this->calculateNextAdvances($advanceBaseTax, $rules);

		$totalCountedAdvancesCzk = $paidAdvancesCzk + $expectedRemainingAdvancesCzk;
		$settlementCzk = $chosen['totalAnnualTaxCzk'] - $totalCountedAdvancesCzk;

		$updatedDividends = $this->dividendTaxCalculator->recalculateCredits(
			$dividends,
			$selectedDividendMode,
			$standardBranch['totalBaseBeforeDeductionsCzk'],
			$standardBranch['standardTaxBeforeCreditCzk'],
		);

		$detail = new TaxIncomeTaxDetailDTO(
			section7TaxBaseCzk: $section7TaxBaseCzk,
			section8TaxBaseCzk: $chosen['section8TaxBaseCzk'],
			section10TaxBaseCzk: $section10TaxBaseCzk,
			totalBaseBeforeDeductionsCzk: $chosen['totalBaseBeforeDeductionsCzk'],
			rawDonationsCzk: max(0, $rawDonationsCzk),
			deductibleDonationsCzk: $chosen['deductibleDonationsCzk'],
			donationsBelowMinimum: $chosen['donationsBelowMinimum'],
			donationsCappedAtMax: $chosen['donationsCappedAtMax'],
			pensionAndLifeDeductionCzk: max(0, $pensionAndLifeDeductionCzk),
			mortgageInterestDeductionCzk: max(0, $mortgageInterestDeductionCzk),
			otherDeductionsCzk: max(0, $otherDeductionsCzk),
			totalDeductionsCzk: $chosen['totalDeductionsCzk'],
			roundedTaxBaseCzk: $chosen['roundedTaxBaseCzk'],
			higherTaxBracketThresholdCzk: $rules->higherTaxBracketThresholdCzk,
			baseIn15BracketCzk: $chosen['baseIn15BracketCzk'],
			baseIn23BracketCzk: $chosen['baseIn23BracketCzk'],
			taxIn15BracketCzk: $chosen['taxIn15BracketCzk'],
			taxIn23BracketCzk: $chosen['taxIn23BracketCzk'],
			standardTaxBeforeCreditCzk: $chosen['standardTaxBeforeCreditCzk'],
			foreignTaxCreditCzk: $chosen['foreignTaxCreditCzk'],
			basicTaxpayerCreditCzk: $rules->basicTaxpayerCreditCzk,
			otherTaxCreditsCzk: max(0, $otherTaxCreditsCzk),
			standardTaxAfterCreditsCzk: $chosen['standardTaxAfterCreditsCzk'],
			separateBase16aTaxAfterCreditCzk: $chosen['separateBase16aTaxAfterCreditCzk'],
			totalAnnualTaxCzk: $chosen['totalAnnualTaxCzk'],
			alternativeModeTotalAnnualTaxCzk: $alternative['totalAnnualTaxCzk'],
			alternativeModeCode: $alternativeMode->value,
			advanceBaseTaxWithoutSection10And16aCzk: $advanceBaseTax,
			nextAdvanceAmountCzk: $nextAdvance['amountCzk'],
			nextAdvancePeriodicity: $nextAdvance['periodicity'],
			nextAdvanceCountPerYear: $nextAdvance['countPerYear'],
		);

		$summary = new TaxInstitutionSummaryDTO(
			institutionCode: TaxInstitutionEnum::TAX_OFFICE->value,
			institutionLabel: TaxInstitutionEnum::TAX_OFFICE->formatShort(),
			annualLiabilityCzk: $chosen['totalAnnualTaxCzk'],
			paidAdvancesCzk: $paidAdvancesCzk,
			expectedRemainingAdvancesCzk: $expectedRemainingAdvancesCzk,
			totalCountedAdvancesCzk: $totalCountedAdvancesCzk,
			settlementCzk: $settlementCzk,
			payableShortfallCzk: max(0, $settlementCzk),
			expectedOverpaymentCzk: max(0, -$settlementCzk),
			nextAdvanceAmountCzk: $nextAdvance['amountCzk'],
			nextAdvancePeriodicity: $nextAdvance['periodicity'],
			nextAdvanceEffectiveFrom: $nextAdvance['effectiveFrom'],
			advancesSourceNote: $advancesSourceNote,
		);

		return [
			'detail' => $detail,
			'summary' => $summary,
			'dividends' => $updatedDividends,
		];
	}

	/**
	 * @return array{amountCzk: int, periodicity: string, countPerYear: int, effectiveFrom: string}
	 */
	public function calculateNextAdvances(int $advanceBaseTaxCzk, TaxYearRules $rules): array
	{
		if ($advanceBaseTaxCzk <= $rules->incomeTaxAdvanceLowerThresholdCzk) {
			return [
				'amountCzk' => 0,
				'periodicity' => 'bez záloh',
				'countPerYear' => 0,
				'effectiveFrom' => '—',
			];
		}

		if ($advanceBaseTaxCzk <= $rules->incomeTaxAdvanceUpperThresholdCzk) {
			$amountCzk = TaxMath::ceilToHundreds(TaxMath::ceilCzk($advanceBaseTaxCzk * 0.40));

			return [
				'amountCzk' => $amountCzk,
				'periodicity' => '2× ročně (15. 6. a 15. 12.)',
				'countPerYear' => 2,
				'effectiveFrom' => 'Po lhůtě pro podání DAP',
			];
		}

		$amountCzk = TaxMath::ceilToHundreds(TaxMath::ceilCzk($advanceBaseTaxCzk * 0.25));

		return [
			'amountCzk' => $amountCzk,
			'periodicity' => '4× ročně (15. 3., 15. 6., 15. 9. a 15. 12.)',
			'countPerYear' => 4,
			'effectiveFrom' => 'Po lhůtě pro podání DAP',
		];
	}

	/**
	 * @return array{
	 *     section8TaxBaseCzk: int,
	 *     totalBaseBeforeDeductionsCzk: int,
	 *     deductibleDonationsCzk: int,
	 *     donationsBelowMinimum: bool,
	 *     donationsCappedAtMax: bool,
	 *     totalDeductionsCzk: int,
	 *     roundedTaxBaseCzk: int,
	 *     baseIn15BracketCzk: int,
	 *     baseIn23BracketCzk: int,
	 *     taxIn15BracketCzk: int,
	 *     taxIn23BracketCzk: int,
	 *     standardTaxBeforeCreditCzk: int,
	 *     foreignTaxCreditCzk: int,
	 *     standardTaxAfterCreditsCzk: int,
	 *     separateBase16aTaxAfterCreditCzk: int,
	 *     totalAnnualTaxCzk: int
	 * }
	 */
	private function calculateBranch(
		int $section7TaxBaseCzk,
		int $section8TaxBaseCzk,
		int $section10TaxBaseCzk,
		TaxDividendsSectionDTO $dividends,
		TaxDividendModeEnum $mode,
		int $rawDonationsCzk,
		int $pensionAndLifeDeductionCzk,
		int $mortgageInterestDeductionCzk,
		int $otherDeductionsCzk,
		int $otherTaxCreditsCzk,
		TaxYearRules $rules,
		bool $includeSeparate16aTax = true,
	): array
	{
		$totalBaseBeforeDeductionsCzk = max(0, $section7TaxBaseCzk)
			+ max(0, $section8TaxBaseCzk)
			+ max(0, $section10TaxBaseCzk);

		$normalizedDonations = max(0, $rawDonationsCzk);
		$donationsQualify = $normalizedDonations >= $rules->giftMinCzk
			|| (
				$totalBaseBeforeDeductionsCzk > 0
				&& $normalizedDonations > $totalBaseBeforeDeductionsCzk * $rules->giftMinBasePercentage
			);
		$maxDonationsCzk = TaxMath::floorCzk($totalBaseBeforeDeductionsCzk * $rules->giftMaxBasePercentage);
		$deductibleDonationsCzk = $donationsQualify ? min($normalizedDonations, $maxDonationsCzk) : 0;
		$donationsBelowMinimum = $normalizedDonations > 0 && !$donationsQualify;
		$donationsCappedAtMax = $donationsQualify && $normalizedDonations > $maxDonationsCzk;

		$totalDeductionsCzk = $deductibleDonationsCzk
			+ max(0, $pensionAndLifeDeductionCzk)
			+ max(0, $mortgageInterestDeductionCzk)
			+ max(0, $otherDeductionsCzk);

		$roundedTaxBaseCzk = TaxMath::floorToHundreds(max(0, $totalBaseBeforeDeductionsCzk - $totalDeductionsCzk));

		$baseIn15BracketCzk = min($roundedTaxBaseCzk, $rules->higherTaxBracketThresholdCzk);
		$baseIn23BracketCzk = max(0, $roundedTaxBaseCzk - $rules->higherTaxBracketThresholdCzk);

		$taxIn15BracketCzk = TaxMath::roundCzk($baseIn15BracketCzk * $rules->basicTaxRate);
		$taxIn23BracketCzk = TaxMath::roundCzk($baseIn23BracketCzk * $rules->higherTaxRate);
		$standardTaxBeforeCreditCzk = $taxIn15BracketCzk + $taxIn23BracketCzk;

		$foreignTaxCreditCzk = 0;
		if ($mode === TaxDividendModeEnum::STANDARD_SECTION_8 && $section8TaxBaseCzk > 0) {
			$recalculatedDividends = $this->dividendTaxCalculator->recalculateCredits(
				$dividends,
				$mode,
				$totalBaseBeforeDeductionsCzk,
				$standardTaxBeforeCreditCzk,
			);
			$foreignTaxCreditCzk = $recalculatedDividends->allowedCreditStandardCzk;
		}

		$taxAfterForeignCredit = max(0, $standardTaxBeforeCreditCzk - $foreignTaxCreditCzk);
		$totalCredits = $rules->basicTaxpayerCreditCzk + max(0, $otherTaxCreditsCzk);
		$standardTaxAfterCreditsCzk = max(0, $taxAfterForeignCredit - $totalCredits);

		$separateBase16aTaxAfterCreditCzk = ($mode === TaxDividendModeEnum::SEPARATE_BASE_16A && $includeSeparate16aTax)
			? $dividends->separateBaseTaxAfterCreditCzk
			: 0;

		return [
			'section8TaxBaseCzk' => $section8TaxBaseCzk,
			'totalBaseBeforeDeductionsCzk' => $totalBaseBeforeDeductionsCzk,
			'deductibleDonationsCzk' => $deductibleDonationsCzk,
			'donationsBelowMinimum' => $donationsBelowMinimum,
			'donationsCappedAtMax' => $donationsCappedAtMax,
			'totalDeductionsCzk' => $totalDeductionsCzk,
			'roundedTaxBaseCzk' => $roundedTaxBaseCzk,
			'baseIn15BracketCzk' => $baseIn15BracketCzk,
			'baseIn23BracketCzk' => $baseIn23BracketCzk,
			'taxIn15BracketCzk' => $taxIn15BracketCzk,
			'taxIn23BracketCzk' => $taxIn23BracketCzk,
			'standardTaxBeforeCreditCzk' => $standardTaxBeforeCreditCzk,
			'foreignTaxCreditCzk' => $foreignTaxCreditCzk,
			'standardTaxAfterCreditsCzk' => $standardTaxAfterCreditsCzk,
			'separateBase16aTaxAfterCreditCzk' => $separateBase16aTaxAfterCreditCzk,
			'totalAnnualTaxCzk' => $standardTaxAfterCreditsCzk + $separateBase16aTaxAfterCreditCzk,
		];
	}

}
