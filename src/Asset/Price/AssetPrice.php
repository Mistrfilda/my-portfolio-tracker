<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;
use App\Currency\CurrencyEnum;

class AssetPrice
{

	public function __construct(
		private readonly Asset $asset,
		private readonly float $currentPrice,
		private readonly CurrencyEnum $currency,
	)
	{
	}

	public function getAsset(): Asset
	{
		return $this->asset;
	}

	public function getCurrentPrice(): float
	{
		return $this->currentPrice;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

}
