<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

class RabbitMQQueueDeclarator
{

	public function __construct(
		private RabbitMQConnectionFactory $connectionFactory,
		private RabbitMQQueueConfigCollection $queueConfigCollection,
	)
	{
	}

	public function declareQueues(): void
	{
		$channel = $this->connectionFactory->createChannel();

		foreach ($this->queueConfigCollection->getAll() as $queue) {
			$channel->queue_declare($queue->name, false, $queue->durable, false, false);
		}

		$channel->close();
	}

}
