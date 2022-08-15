<?php

declare(strict_types = 1);

namespace App\Asset\Price;

class AssetPriceRenderer
{

	public function getGridAssetPriceValue(AssetPrice $assetPrice): string
	{
		return $assetPrice->getPrice() . ' ' . $assetPrice->getCurrency()->format();
	}

}
