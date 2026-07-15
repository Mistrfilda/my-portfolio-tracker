<?php

declare(strict_types = 1);

namespace App\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Utils\TypeValidator;
use Ramsey\Uuid\Uuid;

class JobRequestProcessor
{

	public function __construct(
		private ExpenseTagFacade $expenseTagFacade,
		private StockAssetDividendForecastRecordFacade $stockAssetDividendForecastFacade,
		private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade,
		private StockAiAnalysisGeminiProcessorFacade $stockAiAnalysisGeminiProcessorFacade,
		private PortfolioPeriodStatisticFacade $portfolioPeriodStatisticFacade,
	)
	{
	}

	/**
	 * @param array<string, int|string> $additionalData
	 */
	public function process(JobRequestTypeEnum $type, array $additionalData): void
	{
		switch ($type) {
			case JobRequestTypeEnum::EXPENSE_TAG_PROCESS:
				$this->expenseTagFacade->processExpenses();
				break;
			case JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE:
				$this->stockAssetDividendForecastFacade->recalculate(
					Uuid::fromString(TypeValidator::validateString($additionalData['id'] ?? null)),
				);
				break;
			case JobRequestTypeEnum::STOCK_ASSET_DIVIDEND_FORECAST_RECALCULATE_ALL:
				$this->stockAssetDividendForecastFacade->recalculateAll();
				break;
			case JobRequestTypeEnum::PORTFOLIO_GOAL_UPDATE:
				$this->portfolioGoalUpdateFacade->updateAllActive();
				break;
			case JobRequestTypeEnum::STOCK_AI_ANALYSIS_GEMINI_PROCESS:
				$this->stockAiAnalysisGeminiProcessorFacade->process(
					TypeValidator::validateString($additionalData['runId'] ?? null),
				);
				break;
			case JobRequestTypeEnum::PORTFOLIO_PERIOD_STATISTIC_PROCESS:
				$this->portfolioPeriodStatisticFacade->process(
					TypeValidator::validateString($additionalData['reportId'] ?? null),
				);
				break;
		}
	}

}
