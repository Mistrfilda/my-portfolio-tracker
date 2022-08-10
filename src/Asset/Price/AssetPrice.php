<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;
use App\Currency\CurrencyEnum;

class AssetPrice
{

	public function __construct(
		private readonly Asset $asset,
		private readonly float $price,
		private readonly CurrencyEnum $currency,
	)
	{
	}

	public function getAsset(): Asset
	{
		return $this->asset;
	}

	public function getPrice(): float
	{
		return $this->price;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

}
