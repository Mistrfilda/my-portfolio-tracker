<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Cash\Utils\CashPrice;

class ExpensePriceFilter
{

	public static function format(CashPrice $expensePrice): string
	{
		return CurrencyFilter::format(
			$expensePrice->getAmount(),
			$expensePrice->getCurrencyEnum(),
		);
	}

}
