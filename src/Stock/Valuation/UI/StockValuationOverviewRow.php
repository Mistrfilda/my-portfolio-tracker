<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Stock\Asset\StockAsset;

class StockValuationOverviewRow
{

	/**
	 * @param array<StockValuationOverviewValue> $values
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $values,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockValuationOverviewValue>
	 */
	public function getValues(): array
	{
		return $this->values;
	}

}
