<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetDividendTypeEnum: string implements DatagridRenderableEnum
{

	case REGULAR = 'regular';
	case SPECIAL = 'special';

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::REGULAR->value => self::REGULAR->format(),
			self::SPECIAL->value => self::SPECIAL->format(),
		];
	}

	public function format(): string
	{
		return match ($this) {
			StockAssetDividendTypeEnum::REGULAR => 'Pravidelná',
			StockAssetDividendTypeEnum::SPECIAL => 'Mimořádná',
		};
	}

}
