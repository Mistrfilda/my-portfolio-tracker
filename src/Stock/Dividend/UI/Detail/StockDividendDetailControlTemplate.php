<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI\Detail;

use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockDividendDetailControlTemplate extends BaseControlTemplate
{

	/** @var array<int, array{'label': string, detailDTO: StockAssetDividendDetailDTO}> */
	public array $dividendDetailDTOs;

	public int $lastYear;

	public StockAssetDetailDTO $openStockAssetDetailDTO;

	public float $currentPrice;

}
