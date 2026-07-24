<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI;

use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Safety\StockDividendSafetyScore;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafety;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;

class StockValuationOverviewRow
{

	/**
	 * @param array<StockValuationOverviewValue> $values
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private StockValuationModelConsensus $modelConsensus,
		private array $values,
		private StockValuationMarginOfSafety $marginOfSafety,
		private StockDividendSafetyScore $dividendSafetyScore,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getModelConsensus(): StockValuationModelConsensus
	{
		return $this->modelConsensus;
	}

	/**
	 * @return array<StockValuationOverviewValue>
	 */
	public function getValues(): array
	{
		return $this->values;
	}

	public function getMarginOfSafety(): StockValuationMarginOfSafety
	{
		return $this->marginOfSafety;
	}

	public function getDividendSafetyScore(): StockDividendSafetyScore
	{
		return $this->dividendSafetyScore;
	}

}
