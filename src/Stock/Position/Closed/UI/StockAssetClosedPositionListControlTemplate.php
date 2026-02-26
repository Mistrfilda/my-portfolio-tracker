<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed\UI;

use App\Asset\Price\PriceDiff;
use App\Stock\Position\Closed\StockAssetClossedPositionDTO;
use App\UI\Base\BaseControlTemplate;

class StockAssetClosedPositionListControlTemplate extends BaseControlTemplate
{

	/** @var array<StockAssetClossedPositionDTO> */
	public array $positions;

	public PriceDiff $totalPriceDiffInCzk;

}
