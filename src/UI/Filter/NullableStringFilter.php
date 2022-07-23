<?php

declare(strict_types = 1);

namespace App\UI\Filter;

class NullableStringFilter
{

	public const NULLABLE_PLACEHOLDER = '----';

	public function format(string|null $nullableString): string
	{
		if ($nullableString === null) {
			return self::NULLABLE_PLACEHOLDER;
		}

		return $nullableString;
	}

}
