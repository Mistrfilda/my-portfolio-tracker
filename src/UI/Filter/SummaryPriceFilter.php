<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Asset\Price\SummaryPrice;

class SummaryPriceFilter
{

	public static function format(SummaryPrice $summaryPrice): string
	{
		return CurrencyFilter::format(
			$summaryPrice->getPrice(),
			$summaryPrice->getCurrency(),
		);
	}

}
