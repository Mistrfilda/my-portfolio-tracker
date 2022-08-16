<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Currency\CurrencyEnum;

class CurrencyFilter
{

	public static function format(
		float|int $value,
		CurrencyEnum $currencyEnum,
		int $precision = 2,
	): string
	{
		if (is_int($value)) {
			return number_format($value, 0, '.', ' ') . ' ' . $currencyEnum->format();
		}

		return number_format($value, $precision, '.', ' ') . ' ' . $currencyEnum->format();
	}

}
