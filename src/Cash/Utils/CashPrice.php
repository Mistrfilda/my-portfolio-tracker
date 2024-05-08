<?php

declare(strict_types = 1);

namespace App\Cash\Utils;

use App\Currency\CurrencyEnum;

class CashPrice
{

	public function __construct(
		private float $amount,
		private CurrencyEnum $currencyEnum,
	)
	{
	}

	public function getAmount(): float
	{
		return $this->amount;
	}

	public function getCurrencyEnum(): CurrencyEnum
	{
		return $this->currencyEnum;
	}

}
