<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\JobRequest\JobRequestProcessor;
use App\JobRequest\JobRequestTypeEnum;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Test\UpdatedTestCase;
use Mockery;

class JobRequestProcessorTest extends UpdatedTestCase
{

	public function testProcess(): void
	{
		$expenseTagFacadeMock = Mockery::mock(ExpenseTagFacade::class);
		$stockAssetDividendForecastRecordFacadeMock = Mockery::mock(StockAssetDividendForecastRecordFacade::class);
		$portfolioGoalUpdateFacadeMock = Mockery::mock(PortfolioGoalUpdateFacade::class);
		$stockAiAnalysisGeminiProcessorFacadeMock = Mockery::mock(StockAiAnalysisGeminiProcessorFacade::class);
		$portfolioPeriodStatisticFacadeMock = Mockery::mock(PortfolioPeriodStatisticFacade::class);

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
			$stockAiAnalysisGeminiProcessorFacadeMock,
			$portfolioPeriodStatisticFacadeMock,
		);

		$type = JobRequestTypeEnum::EXPENSE_TAG_PROCESS;
		$additionalData = ['key' => 'value', 'number' => 123];

		$expenseTagFacadeMock
			->shouldReceive('processExpenses')
			->once();

		$jobRequestProcessor->process($type, $additionalData);

		$this->assertTrue(true);
	}

	public function testProcessStockAiAnalysisGeminiProcess(): void
	{
		$expenseTagFacadeMock = Mockery::mock(ExpenseTagFacade::class);
		$stockAssetDividendForecastRecordFacadeMock = Mockery::mock(StockAssetDividendForecastRecordFacade::class);
		$portfolioGoalUpdateFacadeMock = Mockery::mock(PortfolioGoalUpdateFacade::class);
		$stockAiAnalysisGeminiProcessorFacadeMock = Mockery::mock(StockAiAnalysisGeminiProcessorFacade::class);
		$portfolioPeriodStatisticFacadeMock = Mockery::mock(PortfolioPeriodStatisticFacade::class);

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
			$stockAiAnalysisGeminiProcessorFacadeMock,
			$portfolioPeriodStatisticFacadeMock,
		);

		$stockAiAnalysisGeminiProcessorFacadeMock
			->shouldReceive('process')
			->with('run-id')
			->once();

		$jobRequestProcessor->process(JobRequestTypeEnum::STOCK_AI_ANALYSIS_GEMINI_PROCESS, [
			'runId' => 'run-id',
		]);

		$this->assertTrue(true);
	}

	public function testProcessPortfolioPeriodStatistic(): void
	{
		$expenseTagFacadeMock = Mockery::mock(ExpenseTagFacade::class);
		$stockAssetDividendForecastRecordFacadeMock = Mockery::mock(StockAssetDividendForecastRecordFacade::class);
		$portfolioGoalUpdateFacadeMock = Mockery::mock(PortfolioGoalUpdateFacade::class);
		$stockAiAnalysisGeminiProcessorFacadeMock = Mockery::mock(StockAiAnalysisGeminiProcessorFacade::class);
		$portfolioPeriodStatisticFacadeMock = Mockery::mock(PortfolioPeriodStatisticFacade::class);

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
			$stockAiAnalysisGeminiProcessorFacadeMock,
			$portfolioPeriodStatisticFacadeMock,
		);

		$portfolioPeriodStatisticFacadeMock
			->shouldReceive('process')
			->with('report-id')
			->once();

		$jobRequestProcessor->process(JobRequestTypeEnum::PORTFOLIO_PERIOD_STATISTIC_PROCESS, [
			'reportId' => 'report-id',
		]);

		$this->assertTrue(true);
	}

}
