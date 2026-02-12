<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\JobRequest\JobRequestProcessor;
use App\JobRequest\JobRequestTypeEnum;
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

		$jobRequestProcessor = new JobRequestProcessor(
			$expenseTagFacadeMock,
			$stockAssetDividendForecastRecordFacadeMock,
			$portfolioGoalUpdateFacadeMock,
		);

		$type = JobRequestTypeEnum::EXPENSE_TAG_PROCESS;
		$additionalData = ['key' => 'value', 'number' => 123];

		$expenseTagFacadeMock
			->shouldReceive('processExpenses')
			->once();

		$jobRequestProcessor->process($type, $additionalData);

		$this->assertTrue(true);
	}

}
