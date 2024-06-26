<?php

declare(strict_types = 1);

namespace App\Utils\Datetime;

class DatetimeConst
{

	public const SYSTEM_DATETIME_FORMAT = 'd. m. Y H:i:s';

	public const SYSTEM_DATETIME_FORMAT_WITHOUT_SECONDS = 'd. m. Y H:i';

	public const SYSTEM_DATE_FORMAT = 'd. m. Y';

	public const DEFAULT_NULL_DATETIME_PLACEHOLDER = '---';

	public const CZECH_MONTHS = [
		1 => 'Leden',
		2 => 'Únor',
		3 => 'Březen',
		4 => 'Duben',
		5 => 'Květen',
		6 => 'Červen',
		7 => 'Červenec',
		8 => 'Srpen',
		9 => 'Září',
		10 => 'Říjen',
		11 => 'Listopad',
		12 => 'Prosinec',
	];

}
