<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

interface ChartDataProvider
{

	public function getChartData(): ChartDataSet;

	/** @param array<string, string> $parameters */
	public function processParametersFromRequest(array $parameters): void;

	public function getIdForChart(): string;

}
