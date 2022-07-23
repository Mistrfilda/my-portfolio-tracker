<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use function in_array;

class ChartType
{

	public const LINE = 'line';

	public const BAR = 'bar';

	public const DOUGHNUT = 'doughnut';

	public static function typeExists(string $type): void
	{
		if (!in_array($type, self::getAll(), true)) {
			throw new ChartException('Invalid chart type');
		}
	}

	/**
	 * @return array<string>
	 */
	public static function getAll(): array
	{
		return [
			self::LINE,
			self::BAR,
			self::DOUGHNUT,
		];
	}

}
