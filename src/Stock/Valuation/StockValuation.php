<?php

declare(strict_types = 1);

namespace App\Stock\Valuation;

use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Data\StockValuationData;
use Doctrine\Common\Collections\ArrayCollection;

class StockValuation
{

	/** @var ArrayCollection<string, StockValuationData> */
	private ArrayCollection $currentStockValuationDataCollection;

	/**
	 * @param array<string, StockValuationData> $currentStockValuationData
	 */
	public function __construct(
		private StockAsset $stockAsset,
		private array $currentStockValuationData,
	)
	{
		$this->currentStockValuationDataCollection = new ArrayCollection($currentStockValuationData);
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

	/**
	 * @return ArrayCollection<string, StockValuationData>
	 */
	public function getCurrentStockValuationDataCollection(): ArrayCollection
	{
		return $this->currentStockValuationDataCollection;
	}

	public function getValuationDataByType(StockValuationTypeEnum $type): StockValuationData|null
	{
		return $this->currentStockValuationData[$type->value] ?? null;
	}

}
