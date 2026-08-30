<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class FinancialIndependenceDefaults
{

	public function __construct(
		public FinancialIndependenceCalculationInput $calculationInput,
		public ImmutableDateTime $periodStart,
		public ImmutableDateTime $periodEndExclusive,
		public int $expenseTransactionCount,
		public int $contributionRecordCount,
		public float $averageMonthlyInvoicedIncomeCzk,
		public int $invoicedIncomeRecordCount,
		public int $missingCurrencyConversionCount,
		public float|null $historicalAnnualizedTotalReturnPercentage,
		public ImmutableDateTime|null $historicalReturnPeriodStart,
		public ImmutableDateTime|null $historicalReturnPeriodEnd,
	)
	{
	}

}
