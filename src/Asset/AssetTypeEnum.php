<?php

declare(strict_types = 1);

namespace App\Asset;

use App\Stock\Asset\StockAsset;

enum AssetTypeEnum: string
{

	case STOCK = StockAsset::class;

	case PORTU = 'PORTU-TODO';

	case BANK_ACCOUNT = 'BANK-ACOUNT-TODO';

	case CRYPTO = 'CRYPTO-TODO';

}
