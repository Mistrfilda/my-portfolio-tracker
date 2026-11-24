<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxDividendCountrySummaryDTO
{

	public function __construct(
		public string $countryCode,
		public string $countryName,
		public int $grossCzk,
		public int $withheldTaxCzk,
		public int $creditableForeignTaxCzk,
		public int $excessForeignTaxCzk,
		public int $allowedCreditStandardCzk,
		public int $allowedCreditSeparate16aCzk,
	)
	{
	}

}
