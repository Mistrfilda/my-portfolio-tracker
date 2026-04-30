<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use InvalidArgumentException;

class RabbitMQQueueConfigCollection
{

	/**
	 * @param array<RabbitMQQueueConfig> $queues
	 */
	public function __construct(private array $queues)
	{
	}

	/**
	 * @return array<RabbitMQQueueConfig>
	 */
	public function getAll(): array
	{
		return $this->queues;
	}

	public function getByConsumerName(string $consumerName): RabbitMQQueueConfig
	{
		foreach ($this->queues as $queue) {
			if ($queue->consumerName === $consumerName) {
				return $queue;
			}
		}

		throw new InvalidArgumentException(sprintf(
			'RabbitMQ consumer [%s] is not defined. Available consumers: %s',
			$consumerName,
			implode(', ', $this->getConsumerNames()),
		));
	}

	/**
	 * @return array<string>
	 */
	private function getConsumerNames(): array
	{
		return array_map(
			static fn (RabbitMQQueueConfig $queue): string => $queue->consumerName,
			$this->queues,
		);
	}

}
