<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxWorkIncomeMonthItemDTO
{

	public function __construct(
		public int $workYear,
		public int $workMonth,
		public string $label,
		public float $hours,
		public int $hourlyRateCzk,
		public int $amountCzk,
		public bool $isEstimated,
		public bool $isPreviousYearDecember,
		public bool $isMissing,
	)
	{
	}

}
