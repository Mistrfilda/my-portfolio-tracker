<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

enum ChartType: string
{

	case LINE = 'line';

	case BAR = 'bar';

	case DOUGHNUT = 'doughnut';

}
