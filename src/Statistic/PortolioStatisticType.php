<?php

declare(strict_types = 1);

namespace App\Statistic;

enum PortolioStatisticType: string
{

	case TOTAL_INVESTED_IN_CZK = 'total_invested_in_czk';

	case TOTAL_VALUE_IN_CZK = 'total_value_in_czk';

	case TOTAL_PROFIT = 'total_profit';

	case TOTAL_PROFIT_PERCENTAGE = 'total_profit_percentage';

	case CRYPTO_TOTAL_INVESTED_IN_CZK = 'crypto_total_invested_in_czk';

	case CRYPTO_TOTAL_VALUE_IN_CZK = 'crypto_total_value_in_czk';

	public function format(): string
	{
		return match ($this) {
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK => 'Celkově zainvestováno v CZK',
			PortolioStatisticType::TOTAL_VALUE_IN_CZK => 'Celkově hodnota v CZK',
			PortolioStatisticType::TOTAL_PROFIT => 'Celkově zisk v CZK',
			PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE => 'Celkový zisk v procentech',
			PortolioStatisticType::CRYPTO_TOTAL_INVESTED_IN_CZK => 'Celkově zainvestováno v kryptoměnách v CZK',
			PortolioStatisticType::CRYPTO_TOTAL_VALUE_IN_CZK => 'Celková hodnota v kryptoměnách v CZK',
		};
	}

}
