<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail;

use App\Stock\Asset\StockAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockAssetSummaryDetailControlTemplate extends BaseControlTemplate
{

	/** @var array<StockAssetDetailDTO> */
	public array $sortedStockAssetsPositionsDTOs;

	public float $totalInvestedAmountInCzk;

}
