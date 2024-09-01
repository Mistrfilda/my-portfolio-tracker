<?php

declare(strict_types = 1);

namespace App\Statistic;

enum PortfolioStatisticControlTypeEnum: string
{

	case SIMPLE_VALUE = 'simple_value';

	case TABLE = 'table';

}
