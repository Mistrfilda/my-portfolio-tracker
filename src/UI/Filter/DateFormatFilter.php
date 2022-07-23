<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Utils\Datetime\DatetimeConst;
use DateTimeImmutable;

class DateFormatFilter
{

	public function format(DateTimeImmutable|null $datetime): string
	{
		if ($datetime === null) {
			return DatetimeConst::DEFAULT_NULL_DATETIME_PLACEHOLDER;
		}

		return $datetime->format(DatetimeConst::SYSTEM_DATE_FORMAT);
	}

}
