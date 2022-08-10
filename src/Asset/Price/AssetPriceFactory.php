<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;

class AssetPriceFactory
{

	public static function createFromPieceCountPrice(
		Asset $asset,
		int $piecesCount,
		float $price,
	): AssetPrice
	{
		return new AssetPrice(
			$asset,
			$piecesCount * $price,
			$asset->getCurrency(),
		);
	}

}
