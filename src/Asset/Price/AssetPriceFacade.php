<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Currency\CurrencyEnum;

interface AssetPriceFacade
{

	public function getTotalInvestedAmountSummaryPrice(CurrencyEnum $inCurrency): SummaryPrice;

	public function getCurrentPortfolioValueSummaryPrice(CurrencyEnum $inCurrency): SummaryPrice;

	public function includeToTotalValues(): bool;

}
