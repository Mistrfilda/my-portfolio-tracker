<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Price\Exception\PriceDiffException;

class AssetPriceService
{

	public function getAssetPriceDiff(
		AssetPrice $assetPrice1,
		AssetPrice $assetPrice2,
	): PriceDiff
	{
		if ($assetPrice1->getCurrency() !== $assetPrice2->getCurrency()) {
			throw new PriceDiffException('Currency must be same');
		}

		$diffPrice = $assetPrice1->getPrice() - $assetPrice2->getPrice();
		if ($assetPrice2->getPrice() === 0.0) {
			$percentageDiff = 200;
		} else {
			$percentageDiff = $assetPrice1->getPrice() * 100 / $assetPrice2->getPrice();
		}

		return new PriceDiff(
			$diffPrice,
			$percentageDiff,
			$assetPrice1->getCurrency(),
		);
	}

}
