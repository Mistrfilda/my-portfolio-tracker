<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;

class CurrencyNullableConversionFilter
{

	public function __construct(
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function convert(float $value, CurrencyEnum $from, CurrencyEnum $to): string
	{
		try {
			$value = $this->currencyConversionFacade->convertSimpleValue(
				$value,
				$from,
				$to,
			);

			return CurrencyFilter::format($value, $to);
		} catch (MissingCurrencyPairException) {
			return 'N/A';
		}
	}

}
