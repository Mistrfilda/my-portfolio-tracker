<?php

declare(strict_types = 1);

namespace App\JobRequest\RabbitMQ;

use App\JobRequest\JobRequestProcessor;
use App\RabbitMQ\BaseConsumer;
use Tracy\Debugger;

/**
 * @extends BaseConsumer<JobRequestMessage>
 */
class JobRequestConsumer extends BaseConsumer
{

	public function __construct(private JobRequestProcessor $jobRequestProcessor)
	{
	}

	protected function processMessage(object $messageObject): void
	{
		Debugger::log(json_encode($messageObject));
		$this->jobRequestProcessor->process($messageObject->getJobRequestType(), $messageObject->getAdditionalData());
	}

	protected function getMessageClass(): string
	{
		return JobRequestMessage::class;
	}

}
