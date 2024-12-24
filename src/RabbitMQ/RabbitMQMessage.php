<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

interface RabbitMQMessage
{

	public function getRequestId(): string;

	public function getMessageQueuedAtTimestamp(): int;

}
