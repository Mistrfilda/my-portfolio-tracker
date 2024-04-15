<?php

declare(strict_types = 1);

namespace App\Cash\Expense;

use App\Currency\CurrencyEnum;

class ExpensePrice
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
