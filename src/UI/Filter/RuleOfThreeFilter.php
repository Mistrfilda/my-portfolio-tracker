<?php

declare(strict_types = 1);

namespace App\UI\Filter;

class RuleOfThreeFilter
{

	public static function getPercentage(
		int|float $value100Percent,
		int|float $valueX,
	): float|int
	{
		if ($value100Percent === 0) {
			return 0;
		}

		return $value100Percent * 100 / $valueX;
	}

}
