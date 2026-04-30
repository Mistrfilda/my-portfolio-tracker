<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

final readonly class RabbitMQQueueConfig
{

	public function __construct(
		public string $name,
		public string $consumerName,
		public RabbitMQConsumerHandler $consumer,
		public int $prefetchCount = 5,
		public bool $durable = true,
	)
	{
	}

}
