<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConnectionFactory
{

	private AMQPStreamConnection|null $connection = null;

	public function __construct(private RabbitMQConnectionConfig $connectionConfig)
	{
	}

	public function createChannel(): AMQPChannel
	{
		return $this->getConnection()->channel();
	}

	public function close(): void
	{
		if ($this->connection === null || !$this->connection->isConnected()) {
			return;
		}

		$this->connection->close();
		$this->connection = null;
	}

	private function getConnection(): AMQPStreamConnection
	{
		if ($this->connection === null || !$this->connection->isConnected()) {
			$this->connection = new AMQPStreamConnection(
				$this->connectionConfig->host,
				$this->connectionConfig->port,
				$this->connectionConfig->user,
				$this->connectionConfig->password,
			);
		}

		return $this->connection;
	}

}
