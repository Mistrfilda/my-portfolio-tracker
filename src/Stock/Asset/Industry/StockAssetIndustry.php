<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_asset_industry')]
class StockAssetIndustry implements Entity
{

	use SimpleUuid;
	use UpdatedAt;
	use CreatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING)]
	private string $mappingName;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $currentPERatio;

	/** @var ArrayCollection<int, StockAsset> */
	#[ORM\OneToMany(targetEntity: StockAsset::class, mappedBy: 'industry')]
	private Collection $stockAssets;

	public function __construct(
		string $name,
		string $mappingName,
		ImmutableDateTime $now,
		float|null $currentPERatio,
	)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->mappingName = $mappingName;
		$this->currentPERatio = $currentPERatio;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->stockAssets = new ArrayCollection();
	}

	public function update(
		string $name,
		string $mappingName,
		ImmutableDateTime $now,
		float|null $currentPERatio,
	): void
	{
		$this->name = $name;
		$this->mappingName = $mappingName;
		$this->currentPERatio = $currentPERatio;
		$this->updatedAt = $now;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getMappingName(): string
	{
		return $this->mappingName;
	}

	public function getCurrentPERatio(): float|null
	{
		return $this->currentPERatio;
	}

	/**
	 * @return ArrayCollection<int, StockAsset>
	 */
	public function getStockAssets(): Collection
	{
		return $this->stockAssets;
	}

}
