<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Asset\Price\AssetPrice;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
use App\Stock\Valuation\Model\StockValuationModelResponse;

class StockValuationModelTableControlItem
{

	/**
	 * @param array<StockValuationModelResponse> $modelResponses
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $modelResponses,
		private StockValuationModelConsensus $modelConsensus,
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

	public function getCalculatedModelsCount(): int
	{
		return $this->modelConsensus->getValidModelsCount();
	}

	public function getModelsCount(): int
	{
		return $this->modelConsensus->getTotalModelsCount();
	}

	public function getModelConsensus(): StockValuationModelConsensus
	{
		return $this->modelConsensus;
	}

	public function getConsensusPrice(): AssetPrice|null
	{
		return $this->modelConsensus->getPrice();
	}

}
