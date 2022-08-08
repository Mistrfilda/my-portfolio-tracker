<?php

declare(strict_types = 1);

namespace App\Currency;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum CurrencyEnum : string implements DatagridRenderableEnum
{

	case USD = 'USD';

	case EUR = 'EUR';

	case CZK = 'CZK';

	public function format(): string
	{
		return match ($this) {
			CurrencyEnum::USD => 'USD',
			CurrencyEnum::EUR => 'EUR',
			CurrencyEnum::CZK => 'CZK',
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
		];
	}

}
