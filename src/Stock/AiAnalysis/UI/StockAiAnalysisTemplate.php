<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistItem;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestion;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockAiAnalysisTemplate extends BaseAdminPresenterTemplate
{

	public StockAiAnalysisRun|null $run = null;

	public string $generatedPromptForDisplay = '';

	public string $manualOpenPositionsPrompt = '';

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $portfolioResults = [];

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $watchlistResults = [];

	/** @var array<int, StockAiAnalysisStockResult> */
	public array $singleStockResults = [];

	/** @var array<int, StockAiAnalysisActionChecklistItem> */
	public array $dailyBriefActionChecklistItems = [];

	/** @var array<StockAiAnalysisFollowUpQuestion> */
	public array $followUpQuestions = [];

	public int $geminiResponseTempFileCount = 0;

	/** @var array<string, string> */
	public array $portfolioPromptTypes = [];

	public string $stockAssetsDataJson;

}
