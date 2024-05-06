<?php

declare(strict_types = 1);

namespace App\Currency\UI;

use App\Currency\CurrencyEnum;
use App\UI\Base\BaseAdminPresenter;

class CurrencyOverviewPresenter extends BaseAdminPresenter
{

	private CurrencyEnum|null $fromCurrency = null;

	private float|null $amount = null;

	public function renderDefault(): void
	{
		$this->template->heading = 'Měnový přehled';

		$this->template->czkValuesToConvert = [
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

		$this->template->eurValuesToConvert = [
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

		$this->template->usdValuesToConvert = [
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

		$this->template->gbpValuesToConvert = [
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

		$this->template->currencies = CurrencyEnum::getOptionsForAdminSelect();
		$this->template->currencyConvertLink = $this->link(
			'currencyConvert!',
			['fromCurrency' => 'replaceFromCurrency', 'amount' => 'replaceAmount'],
		);
		$this->template->fromCurrency = $this->fromCurrency;
		$this->template->amount = $this->amount;
	}

	public function handleCurrencyConvert(string $fromCurrency, string $amount): void
	{
		$this->fromCurrency = CurrencyEnum::from($fromCurrency);
		$this->amount = (float) $amount;
		$this->redrawControl('calculator');
	}

}
