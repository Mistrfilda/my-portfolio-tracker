<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

interface ExpanseOverviewCategoryControlFactory
{

	public function create(int $year, int|null $month): ExpenseOverviewCategoryControl;

}
