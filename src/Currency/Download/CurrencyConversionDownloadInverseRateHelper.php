<?php

declare(strict_types = 1);

namespace App\Currency\Download;

use App\Currency\CurrencyConversion;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class CurrencyConversionDownloadInverseRateHelper
{

	public function getNewInversedRate(
		CurrencyConversion $conversionToBeConverted,
	): CurrencyConversion
	{
		return new CurrencyConversion(
			$conversionToBeConverted->getToCurrency(),
			$conversionToBeConverted->getFromCurrency(),
			$this->inverseRate($conversionToBeConverted->getCurrentPrice()),
			$conversionToBeConverted->getSource(),
			$conversionToBeConverted->getCreatedAt(),
			$conversionToBeConverted->getForDate(),
		);
	}

	public function updateExistingInversedRate(
		CurrencyConversion $conversionToBeConverted,
		CurrencyConversion $existingConversion,
		ImmutableDateTime $now,
	): void
	{
		$existingConversion->update(
			$this->inverseRate($conversionToBeConverted->getCurrentPrice()),
			$now,
		);
	}

	private function inverseRate(float $rate): float
	{
		return round(1 / $rate, 4);
	}

}
