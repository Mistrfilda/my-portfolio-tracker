<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;
use App\Currency\CurrencyEnum;

class AssetPriceFactory
{

	public static function createFromPieceCountPrice(
		Asset $asset,
		int|float $piecesCount,
		float $price,
		CurrencyEnum|null $currencyEnum = null,
	): AssetPrice
	{
		return new AssetPrice(
			$asset,
			$piecesCount * $price,
			$currencyEnum ?? $asset->getCurrency(),
		);
	}

}
