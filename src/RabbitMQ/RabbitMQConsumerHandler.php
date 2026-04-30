<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

interface RabbitMQConsumerHandler
{

	public function consume(string $payload): RabbitMQConsumeResult;

}
