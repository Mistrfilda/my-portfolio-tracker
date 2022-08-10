<?php

declare(strict_types = 1);

namespace App\Asset\Price;

interface AssetPriceDownloader
{

	/**
	 * @return array<AssetPriceRecord>
	 */
	public function getPriceForAssets(): array;

}
