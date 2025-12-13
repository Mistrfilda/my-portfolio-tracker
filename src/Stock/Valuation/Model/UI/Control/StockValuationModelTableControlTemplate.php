<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Model\UI\Control;

use App\Stock\Asset\StockAsset;
use App\UI\Base\BaseControlTemplate;

class StockValuationModelTableControlTemplate extends BaseControlTemplate
{

	/** @var array<StockValuationModelTableControlItem>  */
	public array $tableControlItems;

	/** @var array<StockAsset> */
	public array $stockAssets;

}
