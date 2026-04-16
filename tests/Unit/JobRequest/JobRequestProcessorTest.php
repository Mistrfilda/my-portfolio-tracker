<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Goal\PortfolioGoalUpdateFacade;
use App\JobRequest\JobRequestProcessor;
use App\JobRequest\JobRequestTypeEnum;
use App\PortfolioReport\PortfolioReportFacade;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Test\UpdatedTestCase;

class JobRequestProcessorTest extends UpdatedTestCase
{

	private JobRequestProcessor $jobRequestProcessor;

	private PortfolioReportFacade $portfolioReportFacade;

	protected function setUp(): void
	{
		parent::setUp();
		$this->portfolioReportFacade = self::createMockWithIgnoreMethods(PortfolioReportFacade::class);

		$this->jobRequestProcessor = new JobRequestProcessor(
			self::createMockWithIgnoreMethods(ExpenseTagFacade::class),
			self::createMockWithIgnoreMethods(StockAssetDividendForecastRecordFacade::class),
			self::createMockWithIgnoreMethods(PortfolioGoalUpdateFacade::class),
			$this->portfolioReportFacade,
		);
	}

	public function testProcessPortfolioReportGenerateDispatchesToPortfolioReportFacade(): void
	{
		$this->portfolioReportFacade->shouldReceive('generate')
			->once()
			->with('c9b0ff8b-f8cc-4a4c-8bb3-f1ff8ce2ee07');

		$this->jobRequestProcessor->process(
			JobRequestTypeEnum::PORTFOLIO_REPORT_GENERATE,
			['id' => 'c9b0ff8b-f8cc-4a4c-8bb3-f1ff8ce2ee07'],
		);

		$this->assertTrue(true);
	}

}
