<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Asset;
use App\Currency\CurrencyEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetPriceEmbeddable
{

	#[ORM\Column(type: Types::FLOAT)]
	private float $price;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	public function __construct(float $price, CurrencyEnum $currency)
	{
		$this->price = $price;
		$this->currency = $currency;
	}

	public function getPrice(): float
	{
		return $this->price;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAssetPrice(Asset $asset): AssetPrice
	{
		return new AssetPrice($asset, $this->price, $this->currency);
	}

}
