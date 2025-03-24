<?php

declare(strict_types = 1);

namespace App\Currency;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum CurrencyEnum : string implements DatagridRenderableEnum
{

	case USD = 'USD';

	case EUR = 'EUR';

	case CZK = 'CZK';

	case GBP = 'GBP';

	case PLN = 'PLN';

	case NOK = 'NOK';

	public function format(): string
	{
		return match ($this) {
			CurrencyEnum::USD => 'USD',
			CurrencyEnum::EUR => 'EUR',
			CurrencyEnum::CZK => 'CZK',
			CurrencyEnum::GBP => 'GBP',
			CurrencyEnum::PLN => 'PLN',
			CurrencyEnum::NOK => 'NOK',
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::CZK->value => 'CZK',
			self::EUR->value => 'EUR',
			self::USD->value => 'USD',
			self::GBP->value => 'GBP',
			self::PLN->value => 'PLN',
			self::NOK->value => 'NOK',
		];
	}

	/**
	 * @return array<self>
	 */
	public static function getAll(): array
	{
		return [
			CurrencyEnum::USD,
			CurrencyEnum::EUR,
			CurrencyEnum::CZK,
			CurrencyEnum::GBP,
			CurrencyEnum::PLN,
			CurrencyEnum::NOK,
		];
	}

	public function processFromWeb(float $value): float
	{
		return match ($this) {
			CurrencyEnum::USD, CurrencyEnum::CZK, CurrencyEnum::EUR, CurrencyEnum::PLN, CurrencyEnum::NOK => $value,
			CurrencyEnum::GBP => GBPCurrencyHelper::formatGBpToGBP($value),
		};
	}

}
