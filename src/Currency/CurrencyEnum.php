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

	public function format(): string
	{
		return match ($this) {
			CurrencyEnum::USD => 'USD',
			CurrencyEnum::EUR => 'EUR',
			CurrencyEnum::CZK => 'CZK',
			CurrencyEnum::GBP => 'GBP',
			CurrencyEnum::PLN => 'PLN',
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
		];
	}

	public function processFromWeb(float $value): float
	{
		return match ($this) {
			CurrencyEnum::USD, CurrencyEnum::CZK, CurrencyEnum::EUR, CurrencyEnum::PLN => $value,
			CurrencyEnum::GBP => GBPCurrencyHelper::formatGBpToGBP($value),
		};
	}

}
