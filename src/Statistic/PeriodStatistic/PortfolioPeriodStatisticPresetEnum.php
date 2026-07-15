<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

enum PortfolioPeriodStatisticPresetEnum: string
{

	case ONE_DAY = 'one_day';

	case SEVEN_DAYS = 'seven_days';

	case THIRTY_DAYS = 'thirty_days';

	case SIXTY_DAYS = 'sixty_days';

	case ONE_YEAR = 'one_year';

	case YEAR_TO_DATE = 'year_to_date';

	case ALL = 'all';

	public function format(): string
	{
		return match ($this) {
			self::ONE_DAY => '1 den',
			self::SEVEN_DAYS => '7 dní',
			self::THIRTY_DAYS => '30 dní',
			self::SIXTY_DAYS => '60 dní',
			self::ONE_YEAR => '365 dní',
			self::YEAR_TO_DATE => 'Od začátku roku',
			self::ALL => 'Celá historie',
		};
	}

	public function getNumberOfDays(): int|null
	{
		return match ($this) {
			self::ONE_DAY => 1,
			self::SEVEN_DAYS => 7,
			self::THIRTY_DAYS => 30,
			self::SIXTY_DAYS => 60,
			self::ONE_YEAR => 365,
			self::YEAR_TO_DATE, self::ALL => null,
		};
	}

}
