<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model;

use App\Asset\Price\AssetPrice;

final readonly class StockValuationScenario
{

	public function __construct(
		public string $label,
		public AssetPrice $price,
		public float $requiredReturn,
		public float $growth,
	)
	{
	}

}
