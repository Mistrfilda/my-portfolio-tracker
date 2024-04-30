<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\List;

use Ramsey\Uuid\UuidInterface;

interface StockAssetListDetailControlFactory
{

	/**
	 * @param array<UuidInterface> $stockAssetsIds
	 */
	public function create(
		array $stockAssetsIds,
		StockAssetListDetailControlEnum $assetDetailControlEnum,
	): StockAssetListDetailControl;

}
