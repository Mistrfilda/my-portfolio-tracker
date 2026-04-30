<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

interface RabbitMQPublisher
{

	/**
	 * @param array<string, mixed> $headers
	 */
	public function publish(string $queueName, string $payload, array $headers = []): void;

}
