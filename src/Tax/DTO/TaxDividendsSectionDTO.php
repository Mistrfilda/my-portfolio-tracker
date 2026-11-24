<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxDividendsSectionDTO
{

	/**
	 * @param list<TaxDividendCountrySummaryDTO> $countries
	 * @param list<TaxDividendItemDTO> $items
	 */
	public function __construct(
		public string $selectedMode,
		public int $domesticGrossCzk,
		public int $domesticTaxCzk,
		public int $foreignGrossCzk,
		public int $foreignWithheldTaxCzk,
		public int $foreignCreditableTaxCzk,
		public int $foreignExcessTaxCzk,
		public int $allowedCreditStandardCzk,
		public int $allowedCreditSeparate16aCzk,
		public int $separateBaseTaxBeforeCreditCzk,
		public int $separateBaseTaxAfterCreditCzk,
		public array $countries,
		public array $items,
	)
	{
	}

}
