<?php

declare(strict_types = 1);

namespace App\Asset\Price;

use App\Admin\AppAdmin;
use App\Currency\CurrencyEnum;

interface AssetPriceFacade
{

	public function getTotalInvestedAmountSummaryPrice(
		CurrencyEnum $inCurrency,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice;

	public function getCurrentPortfolioValueSummaryPrice(
		CurrencyEnum $inCurrency,
		AppAdmin|null $appAdmin = null,
	): SummaryPrice;

	public function includeToTotalValues(): bool;

}
