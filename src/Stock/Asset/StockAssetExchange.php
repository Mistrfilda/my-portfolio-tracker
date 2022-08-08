<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetExchange: string implements DatagridRenderableEnum
{

	case NYSE = 'NYSE';

	case NASDAQ = 'NASDAQ';

	case PRAGUE_STOCK_EXCHANGE = 'PSE';

	public function format(): string
	{
		return match ($this) {
			StockAssetExchange::NYSE => 'NYSE',
			StockAssetExchange::NASDAQ => 'NASDAQ',
			StockAssetExchange::PRAGUE_STOCK_EXCHANGE => 'PSE'
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::NYSE->value => 'NYSE',
			self::NASDAQ->value => 'NASDAQ',
			self::PRAGUE_STOCK_EXCHANGE->value => 'PSE',
		];
	}

}
