<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Currency\CurrencyEnum;

class PriceDiff
{

	public function __construct(
		private float $priceDifference,
		private float $percentageDifference,
		private CurrencyEnum $currencyEnum,
	)
	{
	}

	public function getPriceDifference(): float
	{
		return $this->priceDifference;
	}

	public function getRawPercentageDifference(): float
	{
		return $this->percentageDifference;
	}

	public function getPercentageDifference(): float
	{
		return $this->percentageDifference - 100;
	}

	public function getCurrencyEnum(): CurrencyEnum
	{
		return $this->currencyEnum;
	}

	public function getTrend(): AssetPriceEnum
	{
		if ($this->getPercentageDifference() === 0.0) {
			return AssetPriceEnum::SAME;
		}

		if ($this->getPercentageDifference() < 0) {
			return AssetPriceEnum::DOWN;
		}

		return AssetPriceEnum::UP;
	}

}
