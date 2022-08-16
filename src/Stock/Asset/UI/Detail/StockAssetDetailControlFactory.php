<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use Ramsey\Uuid\UuidInterface;

interface StockAssetDetailControlFactory
{

	/**
	 * @param array<UuidInterface> $stockAssetsIds
	 */
	public function create(array $stockAssetsIds): StockAssetDetailControl;

}
