<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\UI\Filter\CurrencyFilter;

class AssetPriceRenderer
{

	public function getGridAssetPriceValue(AssetPrice $assetPrice): string
	{
		return CurrencyFilter::format(
			$assetPrice->getPrice(),
			$assetPrice->getCurrency(),
		);
	}

}
