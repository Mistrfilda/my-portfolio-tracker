<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxStockSaleItemDTO
{

	public function __construct(
		public string $closedPositionId,
		public string $assetName,
		public string $ticker,
		public int $pieces,
		public string $buyDate,
		public string $sellDate,
		public int $holdingDays,
		public bool $passedTimeTest,
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
		public int $gainCzk,
		public bool $isExempt,
		public string|null $exemptionReason,
		public bool $requiresNotification38v,
		public bool $hasMissingExchangeRate,
	)
	{
	}

}
