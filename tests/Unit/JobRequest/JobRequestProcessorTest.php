<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\JobRequest\JobRequestProcessor;
use App\JobRequest\JobRequestTypeEnum;
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

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
			$stockAiAnalysisGeminiProcessorFacadeMock,
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

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
			$stockAiAnalysisGeminiProcessorFacadeMock,
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

}
