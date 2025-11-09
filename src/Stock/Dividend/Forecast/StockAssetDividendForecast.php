<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_asset_dividend_forecast')]
class StockAssetDividendForecast implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	public const START_YEAR = 2025;

	#[ORM\Column(type: Types::INTEGER)]
	private int $forYear;

	/** @var ArrayCollection<int, StockAssetDividendForecastRecord> */
	#[ORM\OneToMany(
		targetEntity: StockAssetDividendForecastRecord::class,
		mappedBy: 'stockAssetDividendForecast',
		cascade: ['persist', 'remove'],
	)]
	private Collection $records;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetDividendTrendEnum::class)]
	private StockAssetDividendTrendEnum $trend;

	#[ORM\Column(type: Types::STRING, enumType: StockAssetDividendForecastStateEnum::class)]
	private StockAssetDividendForecastStateEnum $state;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
	private ImmutableDateTime|null $lastRecalculatedAt;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $defaultForYear;

	public function __construct(int $forYear, StockAssetDividendTrendEnum $trend, ImmutableDateTime $now)
	{
		$this->id = Uuid::uuid4();
		$this->forYear = $forYear;
		$this->trend = $trend;
		$this->records = new ArrayCollection();
		$this->state = StockAssetDividendForecastStateEnum::PENDING;
		$this->defaultForYear = false;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(StockAssetDividendTrendEnum $trend, ImmutableDateTime $now): void
	{
		$this->trend = $trend;
		$this->updatedAt = $now;
	}

	public function recalculated(ImmutableDateTime $now): void
	{
		$this->lastRecalculatedAt = $now;
		$this->state = StockAssetDividendForecastStateEnum::FINISHED;
	}

	public function defaultForYear(): void
	{
		$this->defaultForYear = true;
	}

	public function removeDefaultForYear(): void
	{
		$this->defaultForYear = false;
	}

	public function getForYear(): int
	{
		return $this->forYear;
	}

	/**
	 * @return array<StockAssetDividendForecastRecord>
	 */
	public function getRecords(): array
	{
		return $this->records->toArray();
	}

	public function getState(): StockAssetDividendForecastStateEnum
	{
		return $this->state;
	}

	public function getTrend(): StockAssetDividendTrendEnum
	{
		return $this->trend;
	}

	public function getLastRecalculatedAt(): ImmutableDateTime|null
	{
		return $this->lastRecalculatedAt;
	}

	public function isDefaultForYear(): bool
	{
		return $this->defaultForYear;
	}

}
