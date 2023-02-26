<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Asset\Asset;
use App\Asset\AssetTypeEnum;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_asset')]
#[ORM\Index(fields: ['assetPriceDownloader'], name: 'type_idx')]
#[ORM\Index(fields: ['exchange'], name: 'exchange_idx')]
#[ORM\Index(fields: ['ticker'], name: 'ticker_idx')]
class StockAsset implements Entity, Asset
{

	use SimpleUuid;
	use UpdatedAt;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetPriceDownloaderEnum::class)]
	private StockAssetPriceDownloaderEnum $assetPriceDownloader;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $ticker;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetExchange::class)]
	private StockAssetExchange $exchange;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $currentAssetPrice;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $priceDownloadedAt;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $isin;

	/** @var ArrayCollection<int, StockPosition> */
	#[ORM\OneToMany(targetEntity: StockPosition::class, mappedBy: 'stockAsset')]
	#[ORM\OrderBy(['orderDate' => 'asc'])]
	private Collection $positions;

	/** @var ArrayCollection<int, StockAssetPriceRecord> */
	#[ORM\OneToMany(targetEntity: StockAssetPriceRecord::class, mappedBy: 'stockAsset')]
	private Collection $priceRecords;

	/** @var ArrayCollection<int, StockAssetDividend> */
	#[ORM\OneToMany(targetEntity: StockAssetDividend::class, mappedBy: 'stockAsset')]
	private Collection $dividends;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetDividendSourceEnum::class, nullable: true)]
	private StockAssetDividendSourceEnum|null $stockAssetDividendSource;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $dividendTax;

	public function __construct(
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
		ImmutableDateTime $now,
		string|null $isin,
		StockAssetDividendSourceEnum|null $stockAssetDividendSource,
		float|null $dividendTax,
	)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->assetPriceDownloader = $assetPriceDownloader;
		$this->ticker = $ticker;
		$this->exchange = $exchange;
		$this->currency = $currency;
		$this->isin = $isin;
		$this->stockAssetDividendSource = $stockAssetDividendSource;
		$this->dividendTax = $dividendTax;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->currentAssetPrice = new AssetPriceEmbeddable(0, $currency);
		$this->priceDownloadedAt = $now;

		$this->positions = new ArrayCollection();
		$this->priceRecords = new ArrayCollection();
		$this->dividends = new ArrayCollection();
	}

	public function update(
		string $name,
		StockAssetPriceDownloaderEnum $assetPriceDownloader,
		string $ticker,
		StockAssetExchange $exchange,
		CurrencyEnum $currency,
		string|null $isin,
		StockAssetDividendSourceEnum|null $stockAssetDividendSource,
		float|null $dividendTax,
		ImmutableDateTime $now,
	): void
	{
		$this->name = $name;
		$this->assetPriceDownloader = $assetPriceDownloader;
		$this->ticker = $ticker;
		$this->exchange = $exchange;
		$this->currency = $currency;
		$this->isin = $isin;
		$this->stockAssetDividendSource = $stockAssetDividendSource;
		$this->dividendTax = $dividendTax;
		$this->updatedAt = $now;
	}

	public function setCurrentPrice(
		StockAssetPriceRecord $stockAssetPriceRecord,
		ImmutableDateTime $now,
	): void
	{
		$this->currentAssetPrice = new AssetPriceEmbeddable(
			$stockAssetPriceRecord->getPrice(),
			$stockAssetPriceRecord->getCurrency(),
		);

		$this->priceDownloadedAt = $now;
	}

	public function getType(): AssetTypeEnum
	{
		return AssetTypeEnum::STOCK;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getAssetPriceDownloader(): StockAssetPriceDownloaderEnum
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

	public function hasPositions(): bool
	{
		return $this->positions->count() > 0;
	}

	/**
	 * @return array<StockPosition>
	 */
	public function getPositions(): array
	{
		return $this->positions->toArray();
	}

	public function getFirstPosition(): StockPosition|null
	{
		return $this->positions->first() === false ? null : $this->positions->first();
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAssetCurrentPrice(): AssetPrice
	{
		return $this->currentAssetPrice->getAssetPrice($this);
	}

	public function getPriceDownloadedAt(): ImmutableDateTime
	{
		return $this->priceDownloadedAt;
	}

	public function getIsin(): string|null
	{
		return $this->isin;
	}

	/**
	 * @return array<int, StockAssetDividend>
	 */
	public function getDividends(): array
	{
		return $this->dividends->toArray();
	}

	public function doesPaysDividends(): bool
	{
		return $this->stockAssetDividendSource !== null;
	}

	public function getStockAssetDividendSource(): StockAssetDividendSourceEnum|null
	{
		return $this->stockAssetDividendSource;
	}

	public function getDividendTax(): float|null
	{
		return $this->dividendTax;
	}

}
