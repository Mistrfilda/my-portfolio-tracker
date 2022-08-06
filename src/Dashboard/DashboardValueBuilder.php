<?php

declare(strict_types = 1);

namespace App\Dashboard;

use App\Currency\CurrencyConversionRepository;
use App\Currency\CurrencyEnum;
use App\UI\Icon\SvgIcon;
use App\UI\Tailwind\TailwindColorConstant;
use App\Utils\Datetime\DatetimeConst;

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
		$eurCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
		);

		$usdCzk = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::USD,
			CurrencyEnum::CZK,
		);

		$eurUsd = $this->currencyConversionRepository->getCurrentCurrencyPairConversion(
			CurrencyEnum::EUR,
			CurrencyEnum::USD,
		);

		return [
			new DashboardValue(
				'EUR - CZK',
				(string) $eurCzk->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::CZECH_CROWN,
				sprintf('Aktualizováno %s', $eurCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
			new DashboardValue(
				'USD - CZK',
				(string) $usdCzk->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::CZECH_CROWN,
				sprintf('Aktualizováno %s', $usdCzk->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
			new DashboardValue(
				'EUR - USD',
				(string) $eurUsd->getCurrentPrice(),
				TailwindColorConstant::BLUE,
				SvgIcon::DOLLAR,
				sprintf('Aktualizováno %s', $eurUsd->getUpdatedAt()->format(DatetimeConst::SYSTEM_DATETIME_FORMAT)),
			),
		];
	}

}
