<?php

declare(strict_types = 1);

namespace App\UI\Filter;

class BooleanFilter
{

	public static function format(bool $value): string
	{
		if ($value) {
			return 'Ano';
		}

		return 'Ne';
	}

}
