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

	public function format(): string
	{
		return match ($this) {
			CurrencyEnum::USD => 'USD',
			CurrencyEnum::EUR => 'EUR',
			CurrencyEnum::CZK => 'CZK',
			CurrencyEnum::GBP => 'GBP',
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::USD->value => 'USD',
			self::EUR->value => 'EUR',
			self::CZK->value => 'CZK',
			self::GBP->value => 'GBP',
		];
	}

	public function processFromWeb(float $value): float
	{
		return match ($this) {
			CurrencyEnum::USD => $value,
			CurrencyEnum::EUR => $value,
			CurrencyEnum::CZK => $value,
			CurrencyEnum::GBP => GBPCurrencyHelper::formatGBpToGBP($value),
		};
	}

}
