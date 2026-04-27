<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\List;

use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockAssetListDetailControlTemplate extends BaseControlTemplate
{

	/** @var array<StockAssetDetailDTO> */
	public array $stockAssetsPositionDTOs;

	/** @var array<StockAssetDetailDTO> */
	public array $sortedStockAssetsPositionsDTOs;

	/** @var array<string, StockAssetDividendDetailDTO> */
	public array $lastDaysDividendDetailDTOs;

	public float $totalInvestedAmountInCzk;

	public StockAssetListDetailControlEnum $assetDetailControlEnum;

}
