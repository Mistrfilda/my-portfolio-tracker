<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

enum StockAssetExchange: string
{

	case NYSE = 'NYSE';

	case NASDAQ = 'NASDAQ';

	case PRAGUE_STOCK_EXCHANGE = 'PSE';

}
