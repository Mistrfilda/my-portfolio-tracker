<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

interface ChartDataProvider
{

	public function getChartData(): ChartData;

}
