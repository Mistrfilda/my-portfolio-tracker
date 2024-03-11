<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;

class CurrencyConversionFilter
{

	public function __construct(
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function convert(float $value, CurrencyEnum $from, CurrencyEnum $to): float
	{
		return $this->currencyConversionFacade->convertSimpleValue(
			$value,
			$from,
			$to,
		);
	}

}
