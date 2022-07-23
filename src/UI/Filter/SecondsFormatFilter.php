<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use function ceil;
use function floor;

class SecondsFormatFilter
{

	public const FORMAT_MINUTES_UP = 'minutes_up';

	public const FORMAT_MINUTES_DOWN = 'minutes_down';

	public function format(int $seconds, string $format = self::FORMAT_MINUTES_DOWN): int
	{
		if ($seconds <= 0) {
			return 0;
		}

		switch ($format) {
			case self::FORMAT_MINUTES_DOWN:
				return (int) floor($seconds / 60);
			case self::FORMAT_MINUTES_UP:
				return (int) ceil($seconds / 60);
			default:
				throw new FilterException('Invalid format');
		}
	}

}
