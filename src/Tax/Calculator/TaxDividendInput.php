<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

final readonly class TaxDividendInput
{

	public function __construct(
		public string $dividendRecordId,
		public string $assetName,
		public string $ticker,
		public string $countryCode,
		public string $paymentDate,
		public string $currency,
		public float $grossAmountOriginal,
		public float $withheldTaxRatePercentage,
		public float $withheldTaxOriginal,
		public float|null $exchangeRate,
		public string|null $exchangeRateDate,
		public int $grossCzk,
		public int $withheldTaxCzk,
		public bool $countryInferredWithoutIsin,
		public bool $hasMissingExchangeRate,
	)
	{
	}

}
