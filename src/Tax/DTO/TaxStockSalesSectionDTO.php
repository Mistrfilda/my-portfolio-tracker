<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxStockSalesSectionDTO
{

	/**
	 * @param list<TaxStockSaleItemDTO> $items
	 */
	public function __construct(
		public int $totalProceedsCzk,
		public int $proceedsExemptionLimitCzk,
		public bool $passedValueTest,
		public int $timeTestExemptProceedsCzk,
		public int|null $timeTestExemptionCapCzk,
		public bool $exceededTimeTestCap,
		public int $taxableProceedsCzk,
		public int $taxableExpensesCzk,
		public int $rawTaxableGainCzk,
		public int $taxBaseSection10Czk,
		public int $notification38vItemCount,
		public array $items,
	)
	{
	}

}
