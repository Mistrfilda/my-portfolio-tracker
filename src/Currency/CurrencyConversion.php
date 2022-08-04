<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Doctrine\CreatedAt;
use App\Doctrine\Entity;
use App\Doctrine\Identifier;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

#[ORM\Entity]
#[ORM\Table('currency_conversion')]
class CurrencyConversion implements Entity
{

	use Identifier;
	use CreatedAt;

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
		$this->forDate = $forDate->setTime(0, 0);
	}

	public function update(float $currentPrice): void
	{
		$this->currentPrice = $currentPrice;
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

}
