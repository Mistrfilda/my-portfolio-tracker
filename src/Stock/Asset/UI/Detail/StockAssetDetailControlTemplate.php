<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockAssetDetailControlTemplate extends BaseControlTemplate
{

	public StockAsset $stockAsset;

	public StockAssetDetailDTO $openStockAssetDetailDTO;

	public StockAssetDetailDTO $closedStockAssetDetailDTO;

}
