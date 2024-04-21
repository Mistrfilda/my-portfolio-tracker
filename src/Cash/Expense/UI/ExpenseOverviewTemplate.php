<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\UI\Base\BaseAdminPresenterTemplate;

class ExpenseOverviewTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<int, int> */
	public array $yearOptions;

	/** @var array<int, string> */
	public array $monthOptions;

	public int $selectedYear;

	public int|null $selectedMonth;

}
