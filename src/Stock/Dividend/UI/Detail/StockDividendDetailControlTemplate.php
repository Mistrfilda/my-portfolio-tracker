<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\UI\Detail;

use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\UI\Base\BaseControlTemplate;

class StockDividendDetailControlTemplate extends BaseControlTemplate
{

	public StockAssetDividendDetailDTO $stockAssetDividendDetailLastDays;

	public StockAssetDividendDetailDTO $stockAssetDividendDetailLastYear;

	public StockAssetDividendDetailDTO $stockAssetDividendDetailThisYear;

	public int $lastYear;

	public int $thisYear;

	public float $currentPrice;

}
