<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Asset\Price\SummaryPrice;

class StockAssetDividendYearSummaryDTO
{

	public function __construct(
		private int $year,
		private SummaryPrice $summaryPriceWithoutTax,
		private SummaryPrice $summaryPriceWithTax,
	)
	{
	}

	public function getYear(): int
	{
		return $this->year;
	}

	public function getSummaryPriceWithTax(): SummaryPrice
	{
		return $this->summaryPriceWithTax;
	}

	public function getSummaryPriceWithoutTax(): SummaryPrice
	{
		return $this->summaryPriceWithoutTax;
	}

}
