<?php

declare(strict_types = 1);

namespace App\Stock\Price;

enum StockAssetPriceDownloaderEnum: string
{

	case PRAGUE_EXCHANGE_DOWNLOADER = 'PSE';

	case TWELVE_DATA = 'TWELVE_DATA';

}
