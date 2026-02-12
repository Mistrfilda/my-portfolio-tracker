<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockAiAnalysisTemplate extends BaseAdminPresenterTemplate
{

	public StockAiAnalysisRun|null $run = null;

}
