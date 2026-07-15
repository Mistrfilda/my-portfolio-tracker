<?php

declare(strict_types = 1);

namespace App\JobRequest\RabbitMQ;

use App\JobRequest\JobRequestProcessor;
use App\RabbitMQ\BaseConsumer;
use Doctrine\ORM\EntityManagerInterface;
use Tracy\Debugger;

/**
 * @extends BaseConsumer<JobRequestMessage>
 */
class JobRequestConsumer extends BaseConsumer
{

	public function __construct(
		private JobRequestProcessor $jobRequestProcessor,
		private EntityManagerInterface $entityManager,
	)
	{
	}

	protected function processMessage(object $messageObject): void
	{
		$this->entityManager->clear();

		try {
			Debugger::log(json_encode($messageObject));
			$this->jobRequestProcessor->process(
				$messageObject->getJobRequestType(),
				$messageObject->getAdditionalData(),
			);
		} finally {
			$this->entityManager->clear();
		}
	}

	protected function getMessageClass(): string
	{
		return JobRequestMessage::class;
	}

}
