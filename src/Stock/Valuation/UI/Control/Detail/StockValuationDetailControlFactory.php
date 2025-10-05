<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\UI\Control\Detail;

use Ramsey\Uuid\UuidInterface;

interface StockValuationDetailControlFactory
{

	public function create(UuidInterface $stockAssetId): StockValuationDetailControl;

}
