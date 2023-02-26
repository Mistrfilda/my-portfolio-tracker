<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetDividendSourceEnum: string implements DatagridRenderableEnum
{

	case NASDAQ_WEB = 'nasdaq_web';

	case MANUAL = 'manual';

	public function format(): string
	{
		return match ($this) {
			StockAssetDividendSourceEnum::NASDAQ_WEB => 'Nasdaq web',
			StockAssetDividendSourceEnum::MANUAL => 'Manual'
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::NASDAQ_WEB->value => 'Nasdaq web',
			self::MANUAL->value => 'Manual',
		];
	}

}
