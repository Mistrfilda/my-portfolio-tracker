<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use App\Currency\CurrencyEnum;
use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\SimpleUuid;
use App\Doctrine\UpdatedAt;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table('stock_valuation_data')]
class StockValuationData implements Entity
{

	use SimpleUuid;
	use UpdatedAt;
	use CreatedAt;

	#[ORM\ManyToOne(targetEntity: StockAsset::class, inversedBy: 'valuations')]
	#[ORM\JoinColumn(nullable: false)]
	private StockAsset $stockAsset;

	#[ORM\Column(type: Types::STRING, enumType: StockValuationTypeEnum::class)]
	private StockValuationTypeEnum $valuationType;

	#[ORM\Column(type: Types::STRING, enumType: StockValuationTypeGroupEnum::class)]
	private StockValuationTypeGroupEnum $typeGroup;

	#[ORM\Column(type: Types::STRING, enumType: StockValuationTypeValueTypeEnum::class)]
	private StockValuationTypeValueTypeEnum $typeValueType;

	#[ORM\Column(type: Types::BOOLEAN)]
	private bool $lastActive;

	#[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
	private ImmutableDateTime $parsedAt;

	#[ORM\Column(type: Types::STRING, nullable: true)]
	private string|null $stringValue;

	#[ORM\Column(type: Types::FLOAT, nullable: true)]
	private float|null $floatValue;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $currency;

	public function __construct(
		StockAsset $stockAsset,
		StockValuationTypeEnum $type,
		StockValuationTypeGroupEnum $typeGroup,
		StockValuationTypeValueTypeEnum $typeValueType,
		ImmutableDateTime $parsedAt,
		string|null $stringValue,
		float|null $floatValue,
		CurrencyEnum $currencyEnum,
		ImmutableDateTime $now,
	)
	{
		$this->id = Uuid::uuid4();
		$this->stockAsset = $stockAsset;
		$this->valuationType = $type;
		$this->typeGroup = $typeGroup;
		$this->typeValueType = $typeValueType;
		$this->lastActive = true;
		$this->parsedAt = $parsedAt;
		$this->stringValue = $stringValue;
		$this->floatValue = $floatValue;
		$this->currency = $currencyEnum;

		$this->createdAt = $now;
		$this->updatedAt = $now;
	}

	public function setLastActive(bool $lastActive): void
	{
		$this->lastActive = $lastActive;
	}

	public function getStockAsset(): StockAsset
	{
		return $this->stockAsset;
	}

	public function getValuationType(): StockValuationTypeEnum
	{
		return $this->valuationType;
	}

	public function getTypeGroup(): StockValuationTypeGroupEnum
	{
		return $this->typeGroup;
	}

	public function getTypeValueType(): StockValuationTypeValueTypeEnum
	{
		return $this->typeValueType;
	}

	public function isLastActive(): bool
	{
		return $this->lastActive;
	}

	public function getParsedAt(): ImmutableDateTime
	{
		return $this->parsedAt;
	}

	public function getStringValue(): string|null
	{
		return $this->stringValue;
	}

	public function getFloatValue(): float|null
	{
		return $this->floatValue;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

}
