<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;

class DashboardValueBuilder
{

	public function __construct(
		private readonly CurrencyConversionRepository $currencyConversionRepository,
	)
	{

	}

	/**
	 * @return array<int, DashboardValue>
	 */
	public function buildValues(): array
	{
		return [
			new DashboardValue(
				'EUR - CZK',
				(string) $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
					CurrencyEnum::EUR,
					CurrencyEnum::CZK,
				)->getCurrentPrice(),
				TailwindColorConstant::EMERALD,
				SvgIcon::EURO,
			),
			new DashboardValue(
				'USD - CZK',
				(string) $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
					CurrencyEnum::USD,
					CurrencyEnum::CZK,
				)->getCurrentPrice(),
				TailwindColorConstant::EMERALD,
				SvgIcon::DOLLAR,
			),
			new DashboardValue(
				'EUR - USD',
				(string) $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
					CurrencyEnum::EUR,
					CurrencyEnum::USD,
				)->getCurrentPrice(),
				TailwindColorConstant::EMERALD,
				SvgIcon::DOLLAR,
			),
		];
	}

}
