<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

final readonly class RabbitMQConnectionConfig
{

	public function __construct(
		public string $host,
		public int $port,
		public string $user,
		public string $password,
	)
	{
	}

}
