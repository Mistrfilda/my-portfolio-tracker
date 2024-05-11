<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI\Detail;

use Ramsey\Uuid\UuidInterface;

interface StockDividendDetailControlFactory
{

	public function create(UuidInterface $stockAssetId): StockDividendDetailControl;

}
