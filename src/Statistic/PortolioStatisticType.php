<?php

declare(strict_types = 1);

namespace App\Statistic;

enum PortolioStatisticType: string
{

	case TOTAL_INVESTED_IN_CZK = 'total_invested_in_czk';

	case TOTAL_VALUE_IN_CZK = 'total_value_in_czk';

	case TOTAL_PROFIT = 'total_profit';

	case TOTAL_PROFIT_PERCENTAGE = 'total_profit_percentage';

	public function format(): string
	{
		return match ($this) {
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK => 'Celkově zainvestováno v CZK',
			PortolioStatisticType::TOTAL_VALUE_IN_CZK => 'Celkově hodnota v CZK',
			PortolioStatisticType::TOTAL_PROFIT => 'Celkově zisk v CZK',
			PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE => 'Celkový zisk v procentech',
		};
	}

}
