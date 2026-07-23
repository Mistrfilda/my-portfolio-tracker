<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceEvent
{

	public function __construct(
		public readonly ImmutableDateTime $date,
		public readonly float $realizedProfit,
		public readonly float $netDividends,
	)
	{
	}

}
