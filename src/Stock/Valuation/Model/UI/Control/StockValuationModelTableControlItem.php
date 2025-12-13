<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModelResponse;

class StockValuationModelTableControlItem
{

	/**
	 * @param array<StockValuationModelResponse> $modelResponses
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $modelResponses,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockValuationModelResponse>
	 */
	public function getModelResponses(): array
	{
		return $this->modelResponses;
	}

}
