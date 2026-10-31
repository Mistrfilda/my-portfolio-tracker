<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use Mistrfilda\Amqp\RabbitMQMessage;

interface RabbitMQDatabaseMessage
{

	public function getRabbitMqMessage(): RabbitMQMessage;

}
