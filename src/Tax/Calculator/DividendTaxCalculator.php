<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use App\Tax\DTO\TaxDividendCountrySummaryDTO;
use App\Tax\DTO\TaxDividendItemDTO;
use App\Tax\DTO\TaxDividendsSectionDTO;
use App\Tax\Rules\TaxDividendModeEnum;
use App\Tax\Rules\TaxTreatyRateProvider;

class DividendTaxCalculator
{

	public function __construct(
		private TaxTreatyRateProvider $treatyRateProvider,
	)
	{
	}

	/**
	 * @param list<TaxDividendInput> $dividends
	 */
	public function calculate(
		array $dividends,
		TaxDividendModeEnum $selectedMode,
		int $standardTotalBaseBeforeDeductionsCzk = 0,
		int $standardTaxBeforeCreditCzk = 0,
	): TaxDividendsSectionDTO
	{
		$domesticGrossCzk = 0;
		$domesticTaxCzk = 0;
		$foreignGrossCzk = 0;
		$foreignWithheldTaxCzk = 0;
		$foreignCreditableTaxCzk = 0;
		$foreignExcessTaxCzk = 0;

		$items = [];
		/**
		 * @var array<string, array{
		 *     countryName: string,
		 *     grossCzk: int,
		 *     withheldTaxCzk: int,
		 *     creditableForeignTaxCzk: int,
		 *     excessForeignTaxCzk: int
		 * }> $byCountry
		 */
		$byCountry = [];

		foreach ($dividends as $dividend) {
			$countryCode = strtoupper($dividend->countryCode);
			$countryName = $this->treatyRateProvider->getCountryName($countryCode);
			$isCzechDomestic = $this->treatyRateProvider->isCzechDomestic($countryCode);
			$treatyMaxRate = $this->treatyRateProvider->getTreatyMaxRate($countryCode);

			if ($isCzechDomestic) {
				$domesticGrossCzk += $dividend->grossCzk;
				$domesticTaxCzk += $dividend->withheldTaxCzk;
				$creditableTaxCzk = 0;
				$excessTaxCzk = 0;
			} else {
				$maxByTreatyCzk = $treatyMaxRate !== null
					? TaxMath::roundCzk($dividend->grossCzk * $treatyMaxRate)
					: 0;
				$creditableTaxCzk = min($dividend->withheldTaxCzk, $maxByTreatyCzk);
				$excessTaxCzk = max(0, $dividend->withheldTaxCzk - $creditableTaxCzk);

				$foreignGrossCzk += $dividend->grossCzk;
				$foreignWithheldTaxCzk += $dividend->withheldTaxCzk;
				$foreignCreditableTaxCzk += $creditableTaxCzk;
				$foreignExcessTaxCzk += $excessTaxCzk;

				if (!isset($byCountry[$countryCode])) {
					$byCountry[$countryCode] = [
						'countryName' => $countryName,
						'grossCzk' => 0,
						'withheldTaxCzk' => 0,
						'creditableForeignTaxCzk' => 0,
						'excessForeignTaxCzk' => 0,
					];
				}

				$byCountry[$countryCode]['grossCzk'] += $dividend->grossCzk;
				$byCountry[$countryCode]['withheldTaxCzk'] += $dividend->withheldTaxCzk;
				$byCountry[$countryCode]['creditableForeignTaxCzk'] += $creditableTaxCzk;
				$byCountry[$countryCode]['excessForeignTaxCzk'] += $excessTaxCzk;
			}

			$items[] = new TaxDividendItemDTO(
				dividendRecordId: $dividend->dividendRecordId,
				assetName: $dividend->assetName,
				ticker: $dividend->ticker,
				countryCode: $countryCode,
				countryName: $countryName,
				paymentDate: $dividend->paymentDate,
				currency: $dividend->currency,
				grossAmountOriginal: $dividend->grossAmountOriginal,
				withheldTaxRatePercentage: $dividend->withheldTaxRatePercentage,
				withheldTaxOriginal: $dividend->withheldTaxOriginal,
				exchangeRate: $dividend->exchangeRate,
				exchangeRateDate: $dividend->exchangeRateDate,
				grossCzk: $dividend->grossCzk,
				withheldTaxCzk: $dividend->withheldTaxCzk,
				treatyMaxRatePercentage: $treatyMaxRate !== null ? round($treatyMaxRate * 100, 2) : null,
				creditableForeignTaxCzk: $creditableTaxCzk,
				excessForeignTaxCzk: $excessTaxCzk,
				isCzechDomestic: $isCzechDomestic,
				countryInferredWithoutIsin: $dividend->countryInferredWithoutIsin,
				hasMissingExchangeRate: $dividend->hasMissingExchangeRate,
			);
		}

		$separateBaseRoundedCzk = TaxMath::floorToHundreds($foreignGrossCzk);
		$separateBaseTaxBeforeCreditCzk = TaxMath::roundCzk($separateBaseRoundedCzk * 0.15);

		$countries = [];
		$allowedCreditStandardTotalCzk = 0;
		$allowedCreditSeparate16aTotalCzk = 0;

		ksort($byCountry);
		foreach ($byCountry as $countryCode => $countryData) {
			$allowedCreditStandard = $this->calculateCzechCreditLimit(
				$countryData['grossCzk'],
				$standardTotalBaseBeforeDeductionsCzk,
				$standardTaxBeforeCreditCzk,
				$countryData['creditableForeignTaxCzk'],
			);

			$allowedCreditSeparate16a = $this->calculateCzechCreditLimit(
				$countryData['grossCzk'],
				$foreignGrossCzk,
				$separateBaseTaxBeforeCreditCzk,
				$countryData['creditableForeignTaxCzk'],
			);

			$allowedCreditStandardTotalCzk += $allowedCreditStandard;
			$allowedCreditSeparate16aTotalCzk += $allowedCreditSeparate16a;

			$countries[] = new TaxDividendCountrySummaryDTO(
				countryCode: $countryCode,
				countryName: $countryData['countryName'],
				grossCzk: $countryData['grossCzk'],
				withheldTaxCzk: $countryData['withheldTaxCzk'],
				creditableForeignTaxCzk: $countryData['creditableForeignTaxCzk'],
				excessForeignTaxCzk: $countryData['excessForeignTaxCzk'],
				allowedCreditStandardCzk: $allowedCreditStandard,
				allowedCreditSeparate16aCzk: $allowedCreditSeparate16a,
			);
		}

		$allowedCreditStandardTotalCzk = min($allowedCreditStandardTotalCzk, max(0, $standardTaxBeforeCreditCzk));
		$allowedCreditSeparate16aTotalCzk = min($allowedCreditSeparate16aTotalCzk, $separateBaseTaxBeforeCreditCzk);
		$separateBaseTaxAfterCreditCzk = max(0, $separateBaseTaxBeforeCreditCzk - $allowedCreditSeparate16aTotalCzk);

		return new TaxDividendsSectionDTO(
			selectedMode: $selectedMode->value,
			domesticGrossCzk: $domesticGrossCzk,
			domesticTaxCzk: $domesticTaxCzk,
			foreignGrossCzk: $foreignGrossCzk,
			foreignWithheldTaxCzk: $foreignWithheldTaxCzk,
			foreignCreditableTaxCzk: $foreignCreditableTaxCzk,
			foreignExcessTaxCzk: $foreignExcessTaxCzk,
			allowedCreditStandardCzk: $allowedCreditStandardTotalCzk,
			allowedCreditSeparate16aCzk: $allowedCreditSeparate16aTotalCzk,
			separateBaseTaxBeforeCreditCzk: $separateBaseTaxBeforeCreditCzk,
			separateBaseTaxAfterCreditCzk: $separateBaseTaxAfterCreditCzk,
			countries: $countries,
			items: $items,
		);
	}

	/**
	 * Recalculates only the country-level credit caps for a precomputed dividend section
	 * against a new standard tax base and tax (used in fast scenario recalculations).
	 */
	public function recalculateCredits(
		TaxDividendsSectionDTO $dividends,
		TaxDividendModeEnum $selectedMode,
		int $standardTotalBaseBeforeDeductionsCzk,
		int $standardTaxBeforeCreditCzk,
	): TaxDividendsSectionDTO
	{
		$countries = [];
		$allowedCreditStandardTotalCzk = 0;

		foreach ($dividends->countries as $country) {
			$allowedCreditStandard = $this->calculateCzechCreditLimit(
				$country->grossCzk,
				$standardTotalBaseBeforeDeductionsCzk,
				$standardTaxBeforeCreditCzk,
				$country->creditableForeignTaxCzk,
			);
			$allowedCreditStandardTotalCzk += $allowedCreditStandard;

			$countries[] = new TaxDividendCountrySummaryDTO(
				countryCode: $country->countryCode,
				countryName: $country->countryName,
				grossCzk: $country->grossCzk,
				withheldTaxCzk: $country->withheldTaxCzk,
				creditableForeignTaxCzk: $country->creditableForeignTaxCzk,
				excessForeignTaxCzk: $country->excessForeignTaxCzk,
				allowedCreditStandardCzk: $allowedCreditStandard,
				allowedCreditSeparate16aCzk: $country->allowedCreditSeparate16aCzk,
			);
		}

		$allowedCreditStandardTotalCzk = min($allowedCreditStandardTotalCzk, max(0, $standardTaxBeforeCreditCzk));

		return new TaxDividendsSectionDTO(
			selectedMode: $selectedMode->value,
			domesticGrossCzk: $dividends->domesticGrossCzk,
			domesticTaxCzk: $dividends->domesticTaxCzk,
			foreignGrossCzk: $dividends->foreignGrossCzk,
			foreignWithheldTaxCzk: $dividends->foreignWithheldTaxCzk,
			foreignCreditableTaxCzk: $dividends->foreignCreditableTaxCzk,
			foreignExcessTaxCzk: $dividends->foreignExcessTaxCzk,
			allowedCreditStandardCzk: $allowedCreditStandardTotalCzk,
			allowedCreditSeparate16aCzk: $dividends->allowedCreditSeparate16aCzk,
			separateBaseTaxBeforeCreditCzk: $dividends->separateBaseTaxBeforeCreditCzk,
			separateBaseTaxAfterCreditCzk: $dividends->separateBaseTaxAfterCreditCzk,
			countries: $countries,
			items: $dividends->items,
		);
	}

	private function calculateCzechCreditLimit(
		int $countryGrossCzk,
		int $totalBaseCzk,
		int $czechTaxBeforeCreditCzk,
		int $creditableForeignTaxCzk,
	): int
	{
		if (
			$countryGrossCzk <= 0
			|| $totalBaseCzk <= 0
			|| $czechTaxBeforeCreditCzk <= 0
			|| $creditableForeignTaxCzk <= 0
		) {
			return 0;
		}

		$ratio = min(1.0, $countryGrossCzk / $totalBaseCzk);
		$czechTaxAttributableCzk = min($czechTaxBeforeCreditCzk, TaxMath::ceilCzk($czechTaxBeforeCreditCzk * $ratio));

		return min($creditableForeignTaxCzk, $czechTaxAttributableCzk);
	}

}
