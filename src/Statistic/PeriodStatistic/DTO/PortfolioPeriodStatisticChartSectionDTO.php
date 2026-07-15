<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

class PortfolioPeriodStatisticChartSectionDTO
{

	/**
	 * @param array<PortfolioPeriodStatisticChartPointDTO> $portfolioValues
	 * @param array<PortfolioPeriodStatisticChartPointDTO> $investedValues
	 * @param array<PortfolioPeriodStatisticChartPointDTO> $dividendsByCompany
	 */
	public function __construct(
		public array $portfolioValues,
		public array $investedValues,
		public array $dividendsByCompany,
	)
	{
	}

}
