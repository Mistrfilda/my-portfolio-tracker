<?php

declare(strict_types = 1);

namespace App\JobRequest\RabbitMQ;

use App\JobRequest\JobRequestTypeEnum;
use App\RabbitMQ\RabbitMQMessage;

class JobRequestMessage implements RabbitMQMessage
{

	/**
	 * @param array<string, string|int> $additionalData
	 */
	public function __construct(
		public string $requestId,
		public int $messageQueuedAtTimestamp,
		public JobRequestTypeEnum $jobRequestType,
		public array $additionalData = [],
	)
	{
	}

	public function getRequestId(): string
	{
		return $this->requestId;
	}

	public function getMessageQueuedAtTimestamp(): int
	{
		return $this->messageQueuedAtTimestamp;
	}

	public function getJobRequestType(): JobRequestTypeEnum
	{
		return $this->jobRequestType;
	}

	/**
	 * @return array<string, string|int>
	 */
	public function getAdditionalData(): array
	{
		return $this->additionalData;
	}

}
