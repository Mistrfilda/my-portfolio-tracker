<?php

declare(strict_types = 1);

namespace App\Dashboard;

enum DashboardValueGroupEnum: string
{

	case CURRENCY = 'currency';

	case TOTAL_VALUES = 'total_values';

	case STOCKS = 'stocks';

	case PORTU = 'portu';

}
