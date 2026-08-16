<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan\UI;

use App\Stock\AiAnalysis\InvestmentPlan\StockAiInvestmentPlan;
use App\UI\Base\BaseAdminPresenterTemplate;

class StockAiInvestmentPlanTemplate extends BaseAdminPresenterTemplate
{

	public StockAiInvestmentPlan|null $plan = null;

	public string $generatedPromptForDisplay = '';

	public string $codexStartPrompt = '';

}
