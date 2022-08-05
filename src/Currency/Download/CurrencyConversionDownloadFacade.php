<?php

declare(strict_types = 1);

namespace App\Currency\Download;

use App\Currency\CurrencyConversion;

interface CurrencyConversionDownloadFacade
{

	/**
	 * @return array<CurrencyConversion>
	 */
	public function downloadNewRates(): array;

	public function getConsoleDescription(): string;

}
