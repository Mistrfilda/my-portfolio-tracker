<?php

declare(strict_types = 1);

namespace App\Stock\Price;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceRecord;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('stock_asset_price_record')]
class StockAssetPriceRecord implements AssetPriceRecord, Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $date;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $price;

	#[ORM\ManyToOne(targetEntity: StockAsset::class, inversedBy: 'priceRecords')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetPriceDownloaderEnum::class)]
	private StockAssetPriceDownloaderEnum $assetPriceDownloader;

	public function __construct(
		ImmutableDateTime $date,
		CurrencyEnum $currency,
		float $price,
		StockAsset $stockAsset,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		ImmutableDateTime $now,
	)
	{
		$this->date = $date;
		$this->currency = $currency;
		$this->price = $price;
		$this->stockAsset = $stockAsset;
		$this->assetPriceDownloader = $assetPriceDownloader;

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

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getAssetPriceDownloader(): StockAssetPriceDownloaderEnum
	{
		return $this->assetPriceDownloader;
	}

	public function getDate(): ImmutableDateTime
	{
		return $this->date;
	}

	public function getAssetPrice(): AssetPrice
	{
		return new AssetPrice($this->stockAsset, $this->price, $this->currency);
	}

}
