<?php

declare(strict_types = 1);

namespace App\Crypto\Price;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceRecord;
use App\Crypto\Asset\CryptoAsset;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('crypto_asset_price_record')]
class CryptoAssetPriceRecord implements AssetPriceRecord, Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: CryptoAsset::class, inversedBy: 'priceRecords')]
	#[ORM\JoinColumn(nullable: false)]
	private CryptoAsset $cryptoAsset;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $date;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $price;

	public function __construct(
		ImmutableDateTime $date,
		CurrencyEnum $currency,
		float $price,
		CryptoAsset $cryptoAsset,
		ImmutableDateTime $now,
	)
	{
		$this->date = $date;
		$this->currency = $currency;
		$this->price = $price;
		$this->cryptoAsset = $cryptoAsset;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function updatePrice(float $price, ImmutableDateTime $now): void
	{
		$this->price = $price;
		$this->updatedAt = $now;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getPrice(): float
	{
		return $this->price;
	}

	public function getCryptoAsset(): CryptoAsset
	{
		return $this->cryptoAsset;
	}

	public function getDate(): ImmutableDateTime
	{
		return $this->date;
	}

	public function getAssetPrice(): AssetPrice
	{
		return new AssetPrice($this->cryptoAsset, $this->price, $this->currency);
	}

}
