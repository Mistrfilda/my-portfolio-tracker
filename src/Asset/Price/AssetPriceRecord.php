<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

interface AssetPriceRecord
{

	public function getDate(): ImmutableDateTime;

	public function getAssetPrice(): AssetPrice;

}
