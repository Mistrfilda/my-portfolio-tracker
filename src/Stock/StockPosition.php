<?php

declare(strict_types = 1);

namespace App\Stock;

use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;

class StockPosition implements AssetPosition
{

	public function getAsset(): Asset
	{
		// TODO: Implement getAsset() method.
	}

	public function getOrderPiecesCount(): int
	{
		// TODO: Implement getOrderPiecesCount() method.
	}

	public function getTotalInvestedAmount(): AssetPrice
	{
		// TODO: Implement getTotalInvestedAmount() method.
	}

	public function getCurrentTotalAmount(): AssetPrice
	{
		// TODO: Implement getCurrentTotalAmount() method.
	}

	public function getPricePerPiece(): AssetPrice
	{
		// TODO: Implement getPricePerPiece() method.
	}

	public function getCurrency(): CurrencyEnum
	{
		// TODO: Implement getCurrency() method.
	}

}
