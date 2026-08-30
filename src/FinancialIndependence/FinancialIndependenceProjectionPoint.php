<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

final readonly class FinancialIndependenceProjectionPoint
{

	public function __construct(
		public int $month,
		public float $portfolioValueCzk,
	)
	{
	}

}
