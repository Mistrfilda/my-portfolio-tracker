<?php

declare(strict_types = 1);

namespace App\Stock\Price;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAssetPriceDownloaderEnum: string implements DatagridRenderableEnum
{

	case PRAGUE_EXCHANGE_DOWNLOADER = 'PSE';

	case TWELVE_DATA = 'TWELVE_DATA';

	case WEB_SCRAP = 'WEB_SCRAP';

	public function format(): string
	{
		return match ($this) {
			StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER => 'PSE',
			StockAssetPriceDownloaderEnum::TWELVE_DATA => 'TWELVE DATA',
			StockAssetPriceDownloaderEnum::WEB_SCRAP => 'WEB SCRAP'
		};
	}

	/**
	 * @return array<string, string>
	 */
	public static function getOptionsForAdminSelect(): array
	{
		return [
			self::PRAGUE_EXCHANGE_DOWNLOADER->value => 'PSE downloader',
			self::TWELVE_DATA->value => 'Twelve data',
			self::WEB_SCRAP->value => 'Web scrap',
		];
	}

}
