<?php

declare(strict_types = 1);

namespace App\Notification\RabbitMQ;

use App\Notification\NotificationSenderFacade;
use App\RabbitMQ\BaseConsumer;
use Tracy\Debugger;

/**
 * @extends BaseConsumer<NotificationMessage>
 */
class NotificationConsumer extends BaseConsumer
{

	public function __construct(
		private NotificationSenderFacade $notificationSenderFacade,
	)
	{
	}

	protected function processMessage(object $messageObject): void
	{
		Debugger::log($messageObject->notificationId);
		$this->notificationSenderFacade->process($messageObject->notificationId);
	}

	protected function getMessageClass(): string
	{
		return NotificationMessage::class;
	}

}
