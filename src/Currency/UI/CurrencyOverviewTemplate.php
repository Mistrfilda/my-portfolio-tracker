<?php

declare(strict_types = 1);

namespace App\Currency\UI;

use App\Currency\CurrencyEnum;
use App\UI\Base\BaseAdminPresenterTemplate;

class CurrencyOverviewTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<string, string> */
	public array $currencies;

	/** @var array<CurrencyEnum> */
	public array $allCurrencies;

	/** @var array<int, array{currency: CurrencyEnum, amounts: array<int, int>}> */
	public array $quickConversionTables;

	public string $currencyConvertLink;

	public CurrencyEnum $fromCurrency;

	public float $amount;

}
