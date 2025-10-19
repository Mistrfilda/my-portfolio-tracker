<?php

declare(strict_types = 1);

namespace App\Monitoring;

enum MonitoringUptimeMonitorEnum: string
{

	case UPDATED_STOCK_DIVIDENDS_COUNT = 'updated_stock_dividends_count';

	case UPDATED_STOCK_PRICES_COUNT = 'updated_stock_prices_count';

	case UPDATED_STOCK_VALUATION_COUNT = 'updated_stock_valuation_count';

}
