<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

class PortfolioPeriodStatisticChartPointDTO
{

	public function __construct(public string $label, public float $value,)
	{
	}

}
