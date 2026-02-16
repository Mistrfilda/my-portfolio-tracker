<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockAiAnalysisTemplate extends BaseAdminPresenterTemplate
{

	public StockAiAnalysisRun|null $run = null;

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $portfolioResults = [];

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $watchlistResults = [];

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $singleStockResults = [];

	public string $stockAssetsDataJson;

}
