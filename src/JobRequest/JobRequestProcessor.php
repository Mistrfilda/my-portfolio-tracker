<?php

declare(strict_types = 1);

namespace App\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Utils\TypeValidator;
use Ramsey\Uuid\Uuid;

class JobRequestProcessor
{

	public function __construct(
		private ExpenseTagFacade $expenseTagFacade,
		private StockAssetDividendForecastRecordFacade $stockAssetDividendForecastFacade,
		private PortfolioGoalUpdateFacade $portfolioGoalUpdateFacade,
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
		}
	}

}
