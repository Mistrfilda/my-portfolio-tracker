<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Cash\Expense\ExpensePrice;

class ExpensePriceFilter
{

	public static function format(ExpensePrice $expensePrice): string
	{
		return CurrencyFilter::format(
			$expensePrice->getAmount(),
			$expensePrice->getCurrencyEnum(),
		);
	}

}
