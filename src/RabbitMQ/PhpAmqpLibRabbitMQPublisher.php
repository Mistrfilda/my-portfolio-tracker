<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class PhpAmqpLibRabbitMQPublisher implements RabbitMQPublisher
{

	public function __construct(private RabbitMQConnectionFactory $connectionFactory)
	{
	}

	/**
	 * @param array<string, mixed> $headers
	 */
	public function publish(string $queueName, string $payload, array $headers = []): void
	{
		$channel = $this->connectionFactory->createChannel();
		$properties = [
			'content_type' => 'application/json',
			'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
		];

		if ($headers !== []) {
			$properties['application_headers'] = new AMQPTable($headers);
		}

		$channel->basic_publish(new AMQPMessage($payload, $properties), '', $queueName);
		$channel->close();
	}

}
