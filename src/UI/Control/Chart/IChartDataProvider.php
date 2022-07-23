<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

interface IChartDataProvider
{

	public function getChartData(): ChartData;

}
