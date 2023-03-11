<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table('stock_asset_dividend')]
class StockAssetDividend implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: StockAsset::class, inversedBy: 'dividends')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $exDate;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $paymentDate;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $declarationDate;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $amount;

	/** @var ArrayCollection<int, StockAssetDividendRecord> */
	#[ORM\OneToMany(targetEntity: StockAssetDividendRecord::class, mappedBy: 'stockAssetDividend')]
	private Collection $records;

	public function __construct(
		StockAsset $stockAsset,
		ImmutableDateTime $exDate,
		ImmutableDateTime|null $paymentDate,
		ImmutableDateTime|null $declarationDate,
		CurrencyEnum $currency,
		float $amount,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->stockAsset = $stockAsset;
		$this->exDate = $exDate;
		$this->paymentDate = $paymentDate;
		$this->declarationDate = $declarationDate;
		$this->currency = $currency;
		$this->amount = $amount;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->records = new ArrayCollection();
	}

	public function update(
		ImmutableDateTime $exDate,
		ImmutableDateTime $paymentDate,
		ImmutableDateTime|null $declarationDate,
		CurrencyEnum $currency,
		float $amount,
		ImmutableDateTime $now,
	): void
	{
		$this->exDate = $exDate;
		$this->paymentDate = $paymentDate;
		$this->declarationDate = $declarationDate;
		$this->currency = $currency;
		$this->amount = $amount;

		$this->updatedAt = $now;
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getExDate(): ImmutableDateTime
	{
		return $this->exDate;
	}

	public function getPaymentDate(): ImmutableDateTime|null
	{
		return $this->paymentDate;
	}

	public function getDeclarationDate(): ImmutableDateTime|null
	{
		return $this->declarationDate;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function getRecords(): array
	{
		return $this->records->toArray();
	}

	public function getStockAssetId(): UuidInterface
	{
		return $this->stockAsset->getId();
	}

	public function isPaid(ImmutableDateTime $now): bool
	{
		if ($this->getPaymentDate() !== null) {
			return $this->getPaymentDate() > $now;
		}

		return $this->getExDate() > $now;
	}

}
