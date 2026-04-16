<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

enum PortfolioReportPeriodTypeEnum: string
{

	case DAILY = 'daily';

	case WEEKLY = 'weekly';

	case MONTHLY = 'monthly';

	case BIMONTHLY = 'bimonthly';

	public function format(): string
	{
		return match ($this) {
			self::DAILY => 'Daily',
			self::WEEKLY => 'Weekly',
			self::MONTHLY => 'Monthly',
			self::BIMONTHLY => 'Bimonthly',
		};
	}

	public function getDateLabelFormat(): string
	{
		return match ($this) {
			self::DAILY => 'd. m. Y',
			self::WEEKLY => 'd. m. Y',
			self::MONTHLY, self::BIMONTHLY => 'm / Y',
		};
	}

}
