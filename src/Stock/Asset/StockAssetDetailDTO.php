<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Stock\Position\StockAssetPositionDetailDTO;

class StockAssetDetailDTO
{

	/**
	 * @param array<StockAssetPositionDetailDTO> $positions
	 */
	public function __construct(
		private readonly StockAsset $stockAsset,
		private readonly array $positions,
		private readonly SummaryPrice $totalInvestedAmount,
		private readonly SummaryPrice $currentAmount,
		private readonly PriceDiff $currentPriceDiff,
	)
	{
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	/**
	 * @return array<StockAssetPositionDetailDTO>
	 */
	public function getPositions(): array
	{
		return $this->positions;
	}

	public function getTotalInvestedAmount(): SummaryPrice
	{
		return $this->totalInvestedAmount;
	}

	public function getCurrentAmount(): SummaryPrice
	{
		return $this->currentAmount;
	}

	public function getCurrentPriceDiff(): PriceDiff
	{
		return $this->currentPriceDiff;
	}

}
