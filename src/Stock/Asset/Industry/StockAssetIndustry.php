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

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $forwardPERatio;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $pegRatio;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $priceToSales;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $priceToBook;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $priceToCashFlow;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $priceToFreeCashFlow;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $marketCap;

	/** @var ArrayCollection<int, StockAsset> */
	#[ORM\OneToMany(targetEntity: StockAsset::class, mappedBy: 'industry')]
	private Collection $stockAssets;

	public function __construct(
		string $name,
		string $mappingName,
		ImmutableDateTime $now,
		float|null $currentPERatio,
		float|null $marketCap,
		float|null $priceToFreeCashFlow,
		float|null $priceToCashFlow,
		float|null $priceToBook,
		float|null $priceToSales,
		float|null $pegRatio,
		float|null $forwardPERatio,
	)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->mappingName = $mappingName;

		$this->marketCap = $marketCap;
		$this->priceToFreeCashFlow = $priceToFreeCashFlow;
		$this->priceToCashFlow = $priceToCashFlow;
		$this->priceToBook = $priceToBook;
		$this->priceToSales = $priceToSales;
		$this->pegRatio = $pegRatio;
		$this->forwardPERatio = $forwardPERatio;
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
		float|null $marketCap,
		float|null $priceToFreeCashFlow,
		float|null $priceToCashFlow,
		float|null $priceToBook,
		float|null $priceToSales,
		float|null $pegRatio,
		float|null $forwardPERatio,
	): void
	{
		$this->name = $name;
		$this->mappingName = $mappingName;
		$this->marketCap = $marketCap;
		$this->priceToFreeCashFlow = $priceToFreeCashFlow;
		$this->priceToCashFlow = $priceToCashFlow;
		$this->priceToBook = $priceToBook;
		$this->priceToSales = $priceToSales;
		$this->pegRatio = $pegRatio;
		$this->forwardPERatio = $forwardPERatio;
		$this->currentPERatio = $currentPERatio;
		$this->updatedAt = $now;
	}

	public function updateValues(
		ImmutableDateTime $now,
		float|null $currentPERatio,
		float|null $marketCap,
		float|null $priceToFreeCashFlow,
		float|null $priceToCashFlow,
		float|null $priceToBook,
		float|null $priceToSales,
		float|null $pegRatio,
		float|null $forwardPERatio,
	): void
	{
		$this->marketCap = $marketCap;
		$this->priceToFreeCashFlow = $priceToFreeCashFlow;
		$this->priceToCashFlow = $priceToCashFlow;
		$this->priceToBook = $priceToBook;
		$this->priceToSales = $priceToSales;
		$this->pegRatio = $pegRatio;
		$this->forwardPERatio = $forwardPERatio;
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

	public function getMarketCap(): float|null
	{
		return $this->marketCap;
	}

	/**
	 * @return ArrayCollection<int, StockAsset>
	 */
	public function getStockAssets(): Collection
	{
		return $this->stockAssets;
	}

	public function getForwardPERatio(): float|null
	{
		return $this->forwardPERatio;
	}

	public function getPegRatio(): float|null
	{
		return $this->pegRatio;
	}

	public function getPriceToSales(): float|null
	{
		return $this->priceToSales;
	}

	public function getPriceToBook(): float|null
	{
		return $this->priceToBook;
	}

	public function getPriceToCashFlow(): float|null
	{
		return $this->priceToCashFlow;
	}

	public function getPriceToFreeCashFlow(): float|null
	{
		return $this->priceToFreeCashFlow;
	}

}
