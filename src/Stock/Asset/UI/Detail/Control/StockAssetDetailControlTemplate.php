<?php

declare(strict_types = 1);

namespace App\Stock\Asset\UI\Detail\Control;

use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\UI\Base\BaseControlTemplate;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\SmartObject;

class StockAssetDetailControlTemplate extends BaseControlTemplate
{

	use SmartObject;

	public StockAsset $stockAsset;

	public StockAssetDetailDTO $openStockAssetDetailDTO;

	public StockAssetDetailDTO $closedStockAssetDetailDTO;

	public ImmutableDateTime $now;

	/** @var array<int, string> */
	public array $chartOptions;

	public int $currentChartDays;

}
