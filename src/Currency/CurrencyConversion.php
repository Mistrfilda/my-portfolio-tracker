<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use App\Doctrine\UpdatedAt;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('currency_conversion')]
#[ORM\UniqueConstraint(name: 'from_to_date_unidx', fields: ['fromCurrency', 'toCurrency', 'forDate'])]
#[ORM\Index(fields: ['fromCurrency', 'toCurrency', 'forDate'], name: 'from_to_currency_date_idx')]
#[ORM\Index(fields: ['fromCurrency', 'toCurrency'], name: 'from_to_currency_idx')]
#[ORM\Index(fields: ['forDate'], name: 'for_date_idx')]
class CurrencyConversion implements Entity
{

	use Identifier;
	use CreatedAt;
	use UpdatedAt;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $fromCurrency;

	#[ORM\Column(type: Types::STRING, enumType: CurrencyEnum::class)]
	private CurrencyEnum $toCurrency;

	#[ORM\Column(type: Types::FLOAT)]
	private float $currentPrice;

	#[ORM\Column(type: Types::STRING, enumType: CurrencySourceEnum::class)]
	private CurrencySourceEnum $source;

	#[ORM\Column(type: Types::DATE_IMMUTABLE)]
	private ImmutableDateTime $forDate;

	public function __construct(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		float $currentPrice,
		CurrencySourceEnum $source,
		ImmutableDateTime $now,
		ImmutableDateTime $forDate,
	)
	{
		$this->fromCurrency = $fromCurrency;
		$this->toCurrency = $toCurrency;
		$this->currentPrice = $currentPrice;
		$this->source = $source;
		$this->createdAt = $now;
		$this->updatedAt = $now;
		$this->forDate = $forDate->setTime(0, 0);
	}

	public function update(float $currentPrice, ImmutableDateTime $now): void
	{
		$this->currentPrice = $currentPrice;
		$this->updatedAt = $now;
	}

	public function getFromCurrency(): CurrencyEnum
	{
		return $this->fromCurrency;
	}

	public function getToCurrency(): CurrencyEnum
	{
		return $this->toCurrency;
	}

	public function getCurrentPrice(): float
	{
		return $this->currentPrice;
	}

	public function getSource(): CurrencySourceEnum
	{
		return $this->source;
	}

	public function getForDate(): ImmutableDateTime
	{
		return $this->forDate;
	}

}
