<?php

declare(strict_types = 1);

namespace App\Asset\Price;

enum AssetPriceDownloaderEnum: string
{

	case PRAGUE_EXCHANGE_DOWNLOADER = 'PSE';

	case TWELVE_DATA = 'TWELVE_DATA';

}
