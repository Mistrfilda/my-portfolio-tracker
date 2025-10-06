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
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Data\StockValuationData;
use App\UI\Filter\RuleOfThreeFilter;
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

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class, nullable: true)]
	private CurrencyEnum|null $brokerDividendCurrency;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $shouldDownloadPrice;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $shouldDownloadValuation;

	/** @var ArrayCollection<int, StockValuationData> */
	#[ORM\OneToMany(targetEntity: StockValuationData::class, mappedBy: 'stockAsset')]
	private Collection $valuations;

	#[ORM\ManyToOne(targetEntity: StockAssetIndustry::class, inversedBy: 'stockAssets')]
	#[ORM\JoinColumn(nullable: true)]
	private StockAssetIndustry|null $industry = null;

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
		CurrencyEnum|null $brokerDividendCurrency,
		bool $shouldDownloadPrice,
		bool $shouldDownloadValuation,
		StockAssetIndustry|null $industry = null,
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
		$this->brokerDividendCurrency = $brokerDividendCurrency;
		$this->shouldDownloadPrice = $shouldDownloadPrice;
		$this->shouldDownloadValuation = $shouldDownloadValuation;
		$this->industry = $industry;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->currentAssetPrice = new AssetPriceEmbeddable(0, $currency);
		$this->priceDownloadedAt = $now;

		$this->positions = new ArrayCollection();
		$this->priceRecords = new ArrayCollection();
		$this->dividends = new ArrayCollection();
		$this->valuations = new ArrayCollection();
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
		CurrencyEnum|null $brokerDividendCurrency,
		ImmutableDateTime $now,
		bool $shouldDownloadPrice,
		bool $shouldDownloadValuation,
		StockAssetIndustry|null $industry = null,
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
		$this->brokerDividendCurrency = $brokerDividendCurrency;
		$this->updatedAt = $now;
		$this->shouldDownloadPrice = $shouldDownloadPrice;
		$this->shouldDownloadValuation = $shouldDownloadValuation;
		$this->industry = $industry;
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
		return $this->shouldDownloadPrice;
	}

	public function hasMultiplePositions(): bool
	{
		return true;
	}

	public function hasPositions(): bool
	{
		return $this->positions->count() > 0;
	}

	public function hasOpenPositions(): bool
	{
		foreach ($this->positions->toArray() as $position) {
			if ($position->isPositionClosed() === false) {
				return true;
			}
		}

		return false;
	}

	public function hasClosedPositions(): bool
	{
		foreach ($this->positions->toArray() as $position) {
			if ($position->isPositionClosed() === true) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<StockPosition>
	 */
	public function getPositions(bool|null $onlyOpenPositions = null): array
	{
		if ($onlyOpenPositions === true) {
			return $this->positions->filter(
				static fn (StockPosition $position) => $position->isPositionClosed() === false,
			)->toArray();
		}

		return $this->positions->toArray();
	}

	/**
	 * @return array<StockPosition>
	 */
	public function getClosedPositions(): array
	{
		return $this->positions->filter(
			static fn (StockPosition $position) => $position->isPositionClosed(),
		)->toArray();
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

	public function getTrend(ImmutableDateTime $date): float
	{
		$priceRecords = $this->priceRecords->filter(
			static fn (StockAssetPriceRecord $stockAssetPriceRecord) => $stockAssetPriceRecord->getDate()->format(
				'Y-m-d',
			) === $date->format(
				'Y-m-d',
			),
		);

		$deductDays = 1;
		while (count($priceRecords) === 0) {
			$modifiedDate = $date->deductDaysFromDatetime($deductDays);
			$priceRecords = $this->priceRecords->filter(
				static fn (StockAssetPriceRecord $stockAssetPriceRecord) => $stockAssetPriceRecord->getDate()->format(
					'Y-m-d',
				) === $modifiedDate->format('Y-m-d'),
			);

			if ($modifiedDate->diff($date)->days > 7) {
				break;
			}

			$deductDays++;
		}

		if (count($priceRecords) === 0) {
			return 0;
		}

		$lastDayPriceRecord = $priceRecords->first();
		assert($lastDayPriceRecord instanceof StockAssetPriceRecord);

		$percentage = RuleOfThreeFilter::getPercentage(
			$this->currentAssetPrice->getPrice(),
			$lastDayPriceRecord->getPrice(),
		);

		return round((float) ($percentage - 100), 2);
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

	public function getBrokerDividendCurrency(): CurrencyEnum|null
	{
		return $this->brokerDividendCurrency;
	}

	public function shouldDownloadValuation(): bool
	{
		return $this->shouldDownloadValuation;
	}

	/**
	 * @return array<StockValuationData>
	 */
	public function getValuations(): array
	{
		return $this->valuations->toArray();
	}

	public function getIndustry(): StockAssetIndustry|null
	{
		return $this->industry;
	}

}
