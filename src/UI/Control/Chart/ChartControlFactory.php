<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

interface ChartControlFactory
{

	public function create(ChartType $type, ChartDataProvider $chartDataProvider): ChartControl;

}
