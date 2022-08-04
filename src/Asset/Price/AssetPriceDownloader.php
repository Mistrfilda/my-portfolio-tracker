<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;

interface AssetPriceDownloader
{

	/**
	 * @param array<Asset> $assets
	 * @return array<AssetPrice>
	 */
	public function getPriceForAssets(array $assets): array;

}
