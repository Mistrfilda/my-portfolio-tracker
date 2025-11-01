<?php

declare(strict_types = 1);

namespace App\JobRequest;

use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\JobRequest\RabbitMQ\JobRequestMessage;
use App\JobRequest\RabbitMQ\JobRequestProducer;
use Mistrfilda\Datetime\DatetimeFactory;

class JobRequestFacade
{

	public function __construct(
		private ExpenseTagFacade $expenseTagFacade,
		private JobRequestProducer $jobRequestProducer,
		private DatetimeFactory $datetimeFactory,
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
		}
	}

	/**
	 * @param array<string, int|string> $additionalData
	 */
	public function addToQueue(JobRequestTypeEnum $type, array $additionalData = []): void
	{
		$now = $this->datetimeFactory->createNow();

		$this->jobRequestProducer->publish(new JobRequestMessage(
			$type->value . '-' . $now->getTimestamp(),
			$now->getTimestamp(),
			$type,
			$additionalData,
		));
	}

}
