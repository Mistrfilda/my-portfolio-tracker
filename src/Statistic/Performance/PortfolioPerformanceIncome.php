<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

class PortfolioPerformanceIncome
{

	public function __construct(
		public readonly float $realizedProfit,
		public readonly float $netDividends,
	)
	{
	}

}
