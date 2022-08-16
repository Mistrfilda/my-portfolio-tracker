<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Asset\Price\Exception\PriceDiffException;

class SummaryPriceService
{

	public function getSummaryPriceDiff(
		SummaryPrice $summaryPrice1,
		SummaryPrice $summaryPrice2,
	): PriceDiff
	{
		if ($summaryPrice1->getCurrency() !== $summaryPrice2->getCurrency()) {
			throw new PriceDiffException('Currency must be same');
		}

		$diffPrice = $summaryPrice1->getPrice() - $summaryPrice2->getPrice();
		$percentageDiff = $summaryPrice2->getPrice() * 100 / $summaryPrice1->getPrice();

		return new PriceDiff(
			$diffPrice,
			$percentageDiff,
			$summaryPrice1->getCurrency(),
		);
	}

}
