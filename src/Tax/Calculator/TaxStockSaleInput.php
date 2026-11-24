<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class TaxStockSaleInput
{

	public function __construct(
		public string $closedPositionId,
		public string $assetName,
		public string $ticker,
		public int $pieces,
		public ImmutableDateTime $buyDate,
		public ImmutableDateTime $sellDate,
		public string $buyCurrency,
		public float $buyAmountOriginal,
		public float|null $buyExchangeRate,
		public string|null $buyExchangeRateDate,
		public int $buyAmountCzk,
		public string $sellCurrency,
		public float $sellAmountOriginal,
		public float|null $sellExchangeRate,
		public string|null $sellExchangeRateDate,
		public int $sellAmountCzk,
		public bool $hasMissingExchangeRate,
	)
	{
	}

}
