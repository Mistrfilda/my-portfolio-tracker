<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxDividendItemDTO
{

	public function __construct(
		public string $dividendRecordId,
		public string $assetName,
		public string $ticker,
		public string $countryCode,
		public string $countryName,
		public string $paymentDate,
		public string $currency,
		public float $grossAmountOriginal,
		public float $withheldTaxRatePercentage,
		public float $withheldTaxOriginal,
		public float|null $exchangeRate,
		public string|null $exchangeRateDate,
		public int $grossCzk,
		public int $withheldTaxCzk,
		public float|null $treatyMaxRatePercentage,
		public int $creditableForeignTaxCzk,
		public int $excessForeignTaxCzk,
		public bool $isCzechDomestic,
		public bool $countryInferredWithoutIsin,
		public bool $hasMissingExchangeRate,
	)
	{
	}

}
