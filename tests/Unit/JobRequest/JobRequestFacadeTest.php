<?php

declare(strict_types = 1);

namespace App\Test\Unit\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\JobRequest\RabbitMQ\JobRequestProducer;
use App\Stock\Dividend\Forecast\StockAssetDividendForecastRecordFacade;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\DatetimeFactory;
use Mockery;

class JobRequestFacadeTest extends UpdatedTestCase
{

	public function testProcess(): void
	{
		$expenseTagFacadeMock = Mockery::mock(ExpenseTagFacade::class);
		$jobRequestProducerMock = Mockery::mock(JobRequestProducer::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$stockAssetDividendForecastRecordFacadeMock = Mockery::mock(StockAssetDividendForecastRecordFacade::class);

		$jobRequestFacade = new JobRequestFacade(
			$expenseTagFacadeMock,
			$jobRequestProducerMock,
			$datetimeFactoryMock,
			$stockAssetDividendForecastRecordFacadeMock,
		);

		$type = JobRequestTypeEnum::EXPENSE_TAG_PROCESS;
		$additionalData = ['key' => 'value', 'number' => 123];

		$expenseTagFacadeMock
			->shouldReceive('processExpenses')
			->once();

		$jobRequestFacade->process($type, $additionalData);

		$this->assertTrue(true);
	}

}
