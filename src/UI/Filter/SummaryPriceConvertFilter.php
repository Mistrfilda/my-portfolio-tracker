<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;

class SummaryPriceConvertFilter
{

	public function __construct(
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function convert(SummaryPrice $summaryPrice, CurrencyEnum $to): string
	{
		return SummaryPriceFilter::format(
			$this->currencyConversionFacade->getConvertedSummaryPrice(
				$summaryPrice,
				$to,
			),
		);
	}

}
