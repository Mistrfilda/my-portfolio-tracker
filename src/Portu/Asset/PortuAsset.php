<?php

declare(strict_types = 1);

namespace App\Portu\Asset;

use App\Asset\Asset;
use App\Asset\AssetTypeEnum;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Portu\Position\PortuPosition;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('portu_asset')]
class PortuAsset implements Asset, Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	/** @var ArrayCollection<int, PortuPosition> */
	#[ORM\OneToMany(targetEntity: PortuPosition::class, mappedBy: 'portuAsset')]
	private Collection $portuPositions;

	public function __construct(string $name, CurrencyEnum $currency, ImmutableDateTime $now)
	{
		$this->id = Uuid::uuid4();

		$this->name = $name;
		$this->currency = $currency;
		$this->createdAt = $now;
		$this->updatedAt = $now;

		$this->portuPositions = new ArrayCollection();
	}

	public function update(string $name, CurrencyEnum $currency, ImmutableDateTime $now): void
	{
		$this->name = $name;
		$this->currency = $currency;
		$this->updatedAt = $now;
	}

	public function getType(): AssetTypeEnum
	{
		return AssetTypeEnum::PORTU;
	}

	public function getName(): string
	{
		return $this->name;
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
	 * @return array<AssetPosition>
	 */
	public function getPositions(): array
	{
		return $this->portuPositions->toArray();
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getAssetCurrentPrice(): AssetPrice
	{
		$assetPrice = new AssetPrice($this, 0, $this->currency);
		foreach ($this->getPositions() as $position) {
			$assetPrice->addAssetPrice($position->getCurrentTotalAmount());
		}

		return $assetPrice;
	}

}
