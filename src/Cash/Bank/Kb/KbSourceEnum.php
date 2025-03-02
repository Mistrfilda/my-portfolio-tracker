<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Kb;

enum KbSourceEnum: string
{

	case PDF = 'pdf';

	case CSV = 'csv';

	/**
	 * @return array<string>
	 */
	public static function getSelectOptions(): array
	{
		return [
			self::PDF->value => 'PDF',
			self::CSV->value => 'CSV',
		];

	}

}
