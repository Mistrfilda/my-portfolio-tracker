<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Asset\Price\PriceDiff;

class StockAssetPositionDetailDTO
{

	public function __construct(
		private readonly StockPosition $stockPosition,
		private readonly PriceDiff $priceDiff,
	)
	{
	}

	public function getStockPosition(): StockPosition
	{
		return $this->stockPosition;
	}

	public function getPriceDiff(): PriceDiff
	{
		return $this->priceDiff;
	}

}
