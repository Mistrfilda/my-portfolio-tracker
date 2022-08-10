<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Asset\Price\AssetPrice;

class AssetPriceFilter
{

	public static function format(AssetPrice $assetPrice): string
	{
		return $assetPrice->getPrice() . ' ' . $assetPrice->getCurrency()->format();
	}

}
