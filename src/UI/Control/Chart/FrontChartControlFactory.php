<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

interface FrontChartControlFactory
{

	public function create(string $type, IChartDataProvider $chartDataProvider): FrontChartControl;

}
