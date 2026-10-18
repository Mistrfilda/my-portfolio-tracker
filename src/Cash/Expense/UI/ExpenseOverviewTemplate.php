<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

class ExpenseOverviewTemplate extends ExpensePresenterTemplate
{

	/** @var array<int, int> */
	public array $yearOptions;

	/** @var array<int, string> */
	public array $monthOptions;

	public int $selectedYear;

	public int|null $selectedMonth;

}
