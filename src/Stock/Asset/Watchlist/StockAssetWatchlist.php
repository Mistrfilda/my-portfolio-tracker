<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'stock_asset_watchlist')]
class StockAssetWatchlist implements Entity
{

	use SimpleUuid;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING)]
	private string $name;

	#[ORM\Column(type: Types::STRING, unique: true)]
	private string $ticker;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $recommendedEntryPrice;

	#[ORM\Column(type: Types::STRING, nullable: true, enumType: CurrencyEnum::class)]
	private CurrencyEnum|null $currency;

	public function __construct(
		string $name,
		string $ticker,
		float|null $recommendedEntryPrice,
		CurrencyEnum|null $currency,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->name = $name;
		$this->ticker = $ticker;
		$this->recommendedEntryPrice = $recommendedEntryPrice;
		$this->currency = $currency;
		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		string $name,
		string $ticker,
		float|null $recommendedEntryPrice,
		CurrencyEnum|null $currency,
		ImmutableDateTime $now,
	): void
	{
		$this->name = $name;
		$this->ticker = $ticker;
		$this->recommendedEntryPrice = $recommendedEntryPrice;
		$this->currency = $currency;
		$this->updatedAt = $now;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getTicker(): string
	{
		return $this->ticker;
	}

	public function getRecommendedEntryPrice(): float|null
	{
		return $this->recommendedEntryPrice;
	}

	public function getCurrency(): CurrencyEnum|null
	{
		return $this->currency;
	}

}
