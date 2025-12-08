<?php

declare(strict_types = 1);

namespace App\Asset;

use App\Crypto\Asset\CryptoAsset;
use App\Portu\Asset\PortuAsset;
use App\Stock\Asset\StockAsset;

enum AssetTypeEnum: string
{

	case STOCK = StockAsset::class;
	case PORTU = PortuAsset::class;
	case CRYPTO = CryptoAsset::class;

}
