<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Utils\Datetime\DatetimeConst;
use DateTimeImmutable;

class DatetimeFormatFilter
{

	public function format(
		DateTimeImmutable|null $datetime,
		string $format = DatetimeConst::SYSTEM_DATETIME_FORMAT,
	): string
	{
		return self::formatValue($datetime, $format);
	}

	public static function formatValue(
		DateTimeImmutable|null $datetime,
		string $format = DatetimeConst::SYSTEM_DATETIME_FORMAT,
	): string
	{
		if ($datetime === null) {
			return DatetimeConst::DEFAULT_NULL_DATETIME_PLACEHOLDER;
		}

		return $datetime->format($format);
	}

}
