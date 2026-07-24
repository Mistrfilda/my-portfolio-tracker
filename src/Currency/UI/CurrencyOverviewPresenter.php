<?php

declare(strict_types = 1);

namespace App\Currency\UI;

use App\Currency\CurrencyEnum;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read CurrencyOverviewTemplate $template
 */
class CurrencyOverviewPresenter extends BaseAdminPresenter
{

	private const CZK_VALUES_TO_CONVERT = [
		1000,
		5000,
		10000,
		15000,
		20000,
		25000,
		30000,
		35000,
		40000,
		45000,
		50000,
	];

	private const FOREIGN_VALUES_TO_CONVERT = [
		100,
		500,
		1000,
		1500,
		2000,
		2500,
		3000,
		3500,
		4000,
		4500,
		5000,
	];

	private CurrencyEnum|null $fromCurrency = null;

	private float|null $amount = null;

	public function renderDefault(): void
	{
		$this->template->heading = 'Měnový přehled';

		$this->template->currencies = CurrencyEnum::getOptionsForAdminSelect();
		$allCurrencies = [];
		$quickConversionTables = [];
		foreach (array_keys($this->template->currencies) as $currencyValue) {
			$currency = CurrencyEnum::from($currencyValue);
			$allCurrencies[] = $currency;
			$quickConversionTables[] = [
				'currency' => $currency,
				'amounts' => $currency === CurrencyEnum::CZK
					? self::CZK_VALUES_TO_CONVERT
					: self::FOREIGN_VALUES_TO_CONVERT,
			];
		}

		$this->template->allCurrencies = $allCurrencies;
		$this->template->quickConversionTables = $quickConversionTables;
		$this->template->currencyConvertLink = $this->link(
			'currencyConvert!',
			['fromCurrency' => 'replaceFromCurrency', 'amount' => 'replaceAmount'],
		);
		$this->template->fromCurrency = $this->fromCurrency ?? CurrencyEnum::CZK;
		$this->template->amount = $this->amount ?? 1000.0;
	}

	public function handleCurrencyConvert(string $fromCurrency, string $amount): void
	{
		$currency = CurrencyEnum::tryFrom($fromCurrency);
		if ($currency === null || !is_numeric($amount)) {
			return;
		}

		$parsedAmount = (float) $amount;
		if (!is_finite($parsedAmount) || $parsedAmount < 0) {
			return;
		}

		$this->fromCurrency = $currency;
		$this->amount = $parsedAmount;
		$this->redrawControl('calculator');
	}

}
