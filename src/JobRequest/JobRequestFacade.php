<?php

declare(strict_types = 1);

namespace App\JobRequest;

use App\JobRequest\RabbitMQ\JobRequestMessage;
use App\JobRequest\RabbitMQ\JobRequestProducer;
use Mistrfilda\Datetime\DatetimeFactory;

class JobRequestFacade
{

	public function __construct(
		private JobRequestProducer $jobRequestProducer,
		private DatetimeFactory $datetimeFactory,
	)
	{
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
