<?php

declare(strict_types = 1);

namespace App\Tax\DTO;

final readonly class TaxBusinessIncomeSectionDTO
{

	/**
	 * @param list<TaxWorkIncomeMonthItemDTO> $items
	 */
	public function __construct(
		public int $actualReceivedIncomeCzk,
		public int $estimatedRemainingIncomeCzk,
		public int $manualAdjustmentCzk,
		public string|null $manualAdjustmentNote,
		public int $autoForecastAnnualIncomeCzk,
		public string $autoForecastPeriodDescription,
		public int $autoForecastMonthsUsed,
		public int|null $customForecastAnnualIncomeCzk,
		public bool $useCustomForecast,
		public int $usedAnnualIncomeCzk,
		public string $flatRateCode,
		public int $flatRatePercentage,
		public int $maxExpensesCzk,
		public int $expensesCzk,
		public int $taxBaseCzk,
		public array $items,
	)
	{
	}

}
