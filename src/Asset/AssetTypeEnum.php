<?php

declare(strict_types = 1);

namespace App\Asset;

enum AssetTypeEnum: int
{

	case STOCK = 1;

	case PORTU = 2;

	case BANK_ACCOUNT = 3;

	case CRYPTO = 4;

}
