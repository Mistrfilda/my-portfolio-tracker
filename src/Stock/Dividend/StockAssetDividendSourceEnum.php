<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetDividendSourceEnum: string implements DatagridRenderableEnum
{

	case WEB = 'web';

	case MANUAL = 'manual';

	public function format(): string
	{
		return match ($this) {
			StockAssetDividendSourceEnum::WEB => 'Web',
			StockAssetDividendSourceEnum::MANUAL => 'Manual'
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::WEB->value => 'Web',
			self::MANUAL->value => 'Manual',
		];
	}

}
