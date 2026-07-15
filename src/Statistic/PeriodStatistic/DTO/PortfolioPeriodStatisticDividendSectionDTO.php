<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

class PortfolioPeriodStatisticDividendSectionDTO
{

	/**
	 * @param array<PortfolioPeriodStatisticDividendDTO> $dividends
	 * @param array<string> $warnings
	 */
	public function __construct(
		public int $count,
		public float $grossTotalCzk,
		public float $netTotalCzk,
		public float $taxTotalCzk,
		public array $dividends,
		public array $warnings = [],
		public bool $partial = false,
	)
	{
	}

}
