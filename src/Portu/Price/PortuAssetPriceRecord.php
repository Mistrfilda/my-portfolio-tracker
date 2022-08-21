<?php

declare(strict_types = 1);

namespace App\Portu\Price;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\AssetPriceEmbeddable;
use App\Asset\Price\AssetPriceRecord;
use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use App\Portu\Position\PortuPosition;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('portu_asset_price_record')]
class PortuAssetPriceRecord implements AssetPriceRecord, Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $date;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $currentValue;

	#[ORM\Embedded(class: AssetPriceEmbeddable::class)]
	private AssetPriceEmbeddable $totalInvestedAmount;

	#[ORM\ManyToOne(targetEntity: PortuPosition::class, inversedBy: 'priceRecords')]
	#[ORM\JoinColumn(nullable: false)]
	private PortuPosition $portuPosition;

	public function __construct(
		ImmutableDateTime $date,
		CurrencyEnum $currency,
		AssetPriceEmbeddable $currentValue,
		AssetPriceEmbeddable $totalInvestedAmount,
		PortuPosition $portuPosition,
		ImmutableDateTime $now,
	)
	{
		$this->date = $date;
		$this->currency = $currency;
		$this->currentValue = $currentValue;
		$this->totalInvestedAmount = $totalInvestedAmount;
		$this->portuPosition = $portuPosition;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function update(
		AssetPriceEmbeddable $currentValue,
		AssetPriceEmbeddable $totalInvestedAmount,
		ImmutableDateTime $now,
	): void
	{
		$this->currentValue = $currentValue;
		$this->totalInvestedAmount = $totalInvestedAmount;
		$this->updatedAt = $now;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getCurrentValue(): AssetPriceEmbeddable
	{
		return $this->currentValue;
	}

	public function getCurrentValueAssetPrice(): AssetPrice
	{
		return $this->currentValue->getAssetPrice($this->portuPosition->getAsset());
	}

	public function getTotalInvestedAmount(): AssetPriceEmbeddable
	{
		return $this->totalInvestedAmount;
	}

	public function getTotalInvestedAmountAssetPrice(): AssetPrice
	{
		return $this->totalInvestedAmount->getAssetPrice($this->portuPosition->getAsset());
	}

	public function getPortuPosition(): PortuPosition
	{
		return $this->portuPosition;
	}

	public function getDate(): ImmutableDateTime
	{
		return $this->date;
	}

	public function getAssetPrice(): AssetPrice
	{
		return $this->totalInvestedAmount->getAssetPrice($this->portuPosition->getAsset());
	}

}
