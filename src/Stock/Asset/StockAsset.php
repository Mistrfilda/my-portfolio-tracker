<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Asset\Asset;
use App\Asset\AssetTypeEnum;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('stock_asset')]
#[ORM\Index(fields: ['assetPriceDownloader'], name: 'type_idx')]
#[ORM\Index(fields: ['exchange'], name: 'exchange_idx')]
#[ORM\Index(fields: ['ticker'], name: 'ticker_idx')]
class StockAsset implements Entity, Asset
{

	use Identifier;
	use UpdatedAt;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetPriceDownloaderEnum::class)]
	private StockAssetPriceDownloaderEnum $assetPriceDownloader;

	#[ORM\Column(type: Types::STRING)]
	private string $ticker;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetExchange::class)]
	private StockAssetExchange $exchange;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	/**
	 * @var ArrayCollection<int, StockPosition>
	 */
	#[ORM\OneToMany(targetEntity: StockPosition::class, mappedBy: 'stockAsset')]
	private ArrayCollection $positions;

	public function __construct(
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
		ImmutableDateTime $now,
	)
	{
		$this->name = $name;
		$this->assetPriceDownloader = $assetPriceDownloader;
		$this->ticker = $ticker;
		$this->exchange = $exchange;
		$this->currency = $currency;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->positions = new ArrayCollection();
	}

	public function getType(): AssetTypeEnum
	{
		return AssetTypeEnum::STOCK;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getAssetPriceDownloader(): StockAssetPriceDownloaderEnum|null
	{
		return $this->assetPriceDownloader;
	}

	public function getTicker(): string
	{
		return $this->ticker;
	}

	public function getExchange(): StockAssetExchange
	{
		return $this->exchange;
	}

	public function shouldBeUpdated(): bool
	{
		return true;
	}

	public function hasMultiplePositions(): bool
	{
		return true;
	}

	/**
	 * @return array<StockPosition>
	 */
	public function getPositions(): array
	{
		return $this->positions->toArray();
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAssetCurrentPrice(): AssetPrice
	{
		// TODO: Implement getAssetCurrentPrice() method.
	}

}
