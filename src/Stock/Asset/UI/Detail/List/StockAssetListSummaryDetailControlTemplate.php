<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\List;

use App\Stock\Asset\StockAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockAssetListSummaryDetailControlTemplate extends BaseControlTemplate
{

	/** @var array<StockAssetDetailDTO> */
	public array $sortedStockAssetsPositionsDTOs;

	public float $totalInvestedAmountInCzk;

}
