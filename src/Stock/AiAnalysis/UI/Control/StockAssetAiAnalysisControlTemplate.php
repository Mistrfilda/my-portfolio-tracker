<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\UI\Base\BaseControlTemplate;

class StockAssetAiAnalysisControlTemplate extends BaseControlTemplate
{

	/** @var array<StockAiAnalysisStockResult> */
	public array $results = [];

}
