<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetExchange: string implements DatagridRenderableEnum
{

	case NYSE = 'NYSE';

	case NASDAQ = 'NASDAQ';

	case PRAGUE_STOCK_EXCHANGE = 'PSE';

	case LSE = 'LSE';

	case VIE = 'VIE';

	case XETRA = 'XETRA';

	case WARSAW = 'WA';

	case EURONEXT_OSLO = 'OL';

	case EURONEXT_AMSTERDAM = 'AS';

	public function format(): string
	{
		return match ($this) {
			StockAssetExchange::NYSE => 'NYSE',
			StockAssetExchange::NASDAQ => 'NASDAQ',
			StockAssetExchange::PRAGUE_STOCK_EXCHANGE => 'PSE',
			StockAssetExchange::LSE => 'LSE',
			StockAssetExchange::VIE => 'VIE',
			StockAssetExchange::XETRA => 'XETRA',
			StockAssetExchange::WARSAW => 'WA',
			StockAssetExchange::EURONEXT_OSLO => 'OL',
			StockAssetExchange::EURONEXT_AMSTERDAM => 'AS',
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
			self::LSE->value => 'LSE',
			self::VIE->value => 'VIE',
			self::XETRA->value => 'XETRA',
			self::WARSAW->value => 'WA',
			self::EURONEXT_OSLO->value => 'OL',
			self::EURONEXT_AMSTERDAM->value => 'AS',
		];
	}

}
