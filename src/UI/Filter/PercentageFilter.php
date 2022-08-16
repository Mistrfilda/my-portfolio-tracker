<?php

declare(strict_types = 1);

namespace App\UI\Filter;

class PercentageFilter
{

	public static function format(float|int $value): string
	{
		return number_format($value, 2) . ' %';
	}

}
