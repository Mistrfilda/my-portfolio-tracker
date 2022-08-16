<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Price\Exception\SummaryPriceException;
use App\Currency\CurrencyEnum;

class SummaryPrice
{

	private readonly CurrencyEnum $currency;

	private float $price = 0;

	private int $counter = 0;

	public function __construct(CurrencyEnum $currency)
	{
		$this->currency = $currency;
	}

	public function addAssetPrice(AssetPrice $assetPrice): void
	{
		if ($assetPrice->getCurrency() !== $this->currency) {
			throw new SummaryPriceException(
				sprintf(
					'Different currency %s passed to summary - expected %s',
					$assetPrice->getCurrency()->format(),
					$this->currency->format(),
				),
			);
		}

		$this->price += $assetPrice->getPrice();
		$this->counter++;
	}

	public function getCurrency(): CurrencyEnum
	{
		return $this->currency;
	}

	public function getRoundedPrice(): int
	{
		return (int) $this->price;
	}

	public function getPrice(): float
	{
		return $this->price;
	}

	public function getCounter(): int
	{
		return $this->counter;
	}

}
