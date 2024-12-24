<?php

declare(strict_types = 1);

namespace App\Notification\RabbitMQ;

use App\RabbitMQ\BaseProducer;

/**
 * @extends BaseProducer<NotificationMessage>
 */
class NotificationProducer extends BaseProducer
{

	protected function getMessageClass(): string
	{
		return NotificationMessage::class;
	}

}
