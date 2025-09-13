<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;

class StockValuation
{

	/**
	 * @param array<string, StockValuationData> $currentStockValuationData
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $currentStockValuationData,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<string, StockValuationData>
	 */
	public function getCurrentStockValuationData(): array
	{
		return $this->currentStockValuationData;
	}

}
