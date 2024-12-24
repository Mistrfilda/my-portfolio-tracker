<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

interface RabbitMQDatabaseMessage
{

	public function getRabbitMqMessage(): RabbitMQMessage;

}
