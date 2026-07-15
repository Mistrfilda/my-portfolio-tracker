<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

enum PortfolioPeriodStatisticChartTypeEnum: string
{

	case PORTFOLIO_VALUE = 'portfolio_value';

	case DIVIDENDS_BY_COMPANY = 'dividends_by_company';

}
