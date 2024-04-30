<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use Ramsey\Uuid\UuidInterface;

interface StockAssetDetailControlFactory
{

	public function create(UuidInterface $id): StockAssetDetailControl;

}
