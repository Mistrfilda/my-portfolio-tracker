<?php

declare(strict_types = 1);

namespace App\Portu\Position;

use App\Admin\AppAdmin;
use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Portu\Asset\PortuAsset;
use App\Portu\Price\PortuAssetPriceRecord;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('portu_position')]
class PortuPosition implements AssetPosition, Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\ManyToOne(targetEntity: PortuAsset::class, inversedBy: 'portuPositions')]
	#[ORM\JoinColumn(nullable: false)]
	private PortuAsset $portuAsset;

	#[ORM\ManyToOne(targetEntity: AppAdmin::class)]
	#[ORM\JoinColumn(nullable: false)]
	private AppAdmin $appAdmin;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $startDate;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $startInvestment;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $monthlyIncrease;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $currentValue;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedToThisDate;

	/** @var ArrayCollection<int, PortuAssetPriceRecord> */
	#[ORM\OneToMany(targetEntity: PortuAssetPriceRecord::class, mappedBy: 'portuPosition')]
	private Collection $priceRecords;

	public function __construct(
		PortuAsset $portuAsset,
		AppAdmin $appAdmin,
		ImmutableDateTime $startDate,
		AssetPriceEmbeddable $startInvestment,
		AssetPriceEmbeddable $monthlyIncrease,
		AssetPriceEmbeddable $currentValue,
		AssetPriceEmbeddable $totalInvestedToThisDate,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();

		$this->portuAsset = $portuAsset;
		$this->appAdmin = $appAdmin;
		$this->startDate = $startDate;
		$this->startInvestment = $startInvestment;
		$this->monthlyIncrease = $monthlyIncrease;
		$this->currentValue = $currentValue;
		$this->totalInvestedToThisDate = $totalInvestedToThisDate;

		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->priceRecords = new ArrayCollection();
	}

	public function update(
		PortuAsset $portuAsset,
		AppAdmin $appAdmin,
		ImmutableDateTime $startDate,
		AssetPriceEmbeddable $startInvestment,
		AssetPriceEmbeddable $monthlyIncrease,
		AssetPriceEmbeddable $currentValue,
		AssetPriceEmbeddable $totalInvestedToThisDate,
		ImmutableDateTime $now,
	): void
	{
		$this->portuAsset = $portuAsset;
		$this->appAdmin = $appAdmin;
		$this->startDate = $startDate;
		$this->startInvestment = $startInvestment;
		$this->monthlyIncrease = $monthlyIncrease;
		$this->currentValue = $currentValue;
		$this->totalInvestedToThisDate = $totalInvestedToThisDate;
		$this->updatedAt = $now;
	}

	public function updateCurrentValue(
		AssetPriceEmbeddable $currentValue,
		AssetPriceEmbeddable $totalInvestedToThisDate,
	): void
	{
		$this->currentValue = $currentValue;
		$this->totalInvestedToThisDate = $totalInvestedToThisDate;
	}

	public function getAsset(): Asset
	{
		return $this->portuAsset;
	}

	public function getAppAdmin(): AppAdmin
	{
		return $this->appAdmin;
	}

	public function getOrderPiecesCount(): int
	{
		return 1;
	}

	public function getTotalInvestedAmount(): AssetPrice
	{
		return $this->totalInvestedToThisDate->getAssetPrice($this->portuAsset);
	}

	public function getCurrentTotalAmount(): AssetPrice
	{
		return $this->currentValue->getAssetPrice($this->portuAsset);
	}

	public function getPricePerPiece(): AssetPrice
	{
		return $this->getTotalInvestedAmount();
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->portuAsset->getCurrency();
	}

	public function getTotalInvestedAmountInBrokerCurrency(): AssetPrice
	{
		return $this->getTotalInvestedAmount();
	}

	public function getPortuAsset(): PortuAsset
	{
		return $this->portuAsset;
	}

	public function getStartDate(): ImmutableDateTime
	{
		return $this->startDate;
	}

	public function getStartInvestment(): AssetPrice
	{
		return $this->startInvestment->getAssetPrice($this->portuAsset);
	}

	public function getMonthlyIncrease(): AssetPrice
	{
		return $this->monthlyIncrease->getAssetPrice($this->portuAsset);
	}

	public function getCurrentValue(): AssetPrice
	{
		return $this->currentValue->getAssetPrice($this->portuAsset);
	}

	public function getTotalInvestedToThisDate(): AssetPrice
	{
		return $this->totalInvestedToThisDate->getAssetPrice($this->portuAsset);
	}

	/**
	 * @return array<PortuAssetPriceRecord>
	 */
	public function getPriceRecords(): array
	{
		return $this->priceRecords->toArray();
	}

	public function getPortuAssetId(): string
	{
		return $this->portuAsset->getId()->toString();
	}

}
