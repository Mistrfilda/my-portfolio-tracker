<?php

declare(strict_types = 1);

namespace App\Notification\RabbitMQ;

use App\RabbitMQ\RabbitMQMessage;

class NotificationMessage implements RabbitMQMessage
{

	public function __construct(
		public string $requestId,
		public int $messageQueuedAtTimestamp,
		public string $notificationId,
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

}
