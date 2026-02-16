<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI\Control;

use App\Asset\Price\AssetPrice;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\Model\StockValuationModelResponse;
use App\UI\Base\BaseControlTemplate;

class StockAiValuationComparisonControlTemplate extends BaseControlTemplate
{

	public StockAsset $stockAsset;

	/** @var array<StockValuationModelResponse> */
	public array $stockValuationModelResponses;

	public AssetPrice $averageModelPrice;

	public StockAiAnalysisStockResult|null $aiResult;

}
