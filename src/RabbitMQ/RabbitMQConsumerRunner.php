<?php

declare(strict_types = 1);

namespace App\RabbitMQ;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitMQConsumerRunner
{

	public function __construct(
		private RabbitMQConnectionFactory $connectionFactory,
		private RabbitMQQueueConfigCollection $queueConfigCollection,
	)
	{
	}

	public function consume(string $consumerName, int|null $secondsToLive = null): void
	{
		$queue = $this->queueConfigCollection->getByConsumerName($consumerName);
		$channel = $this->connectionFactory->createChannel();
		$startedAt = time();

		try {
			$channel->basic_qos(0, $queue->prefetchCount, false);
			$channel->basic_consume(
				$queue->name,
				'',
				false,
				false,
				false,
				false,
				function (AMQPMessage $message) use ($queue, $channel): void {
					$this->processMessage($queue, $channel, $message);
				},
			);

			while ($channel->is_consuming() && !$this->isTimeLimitReached($startedAt, $secondsToLive)) {
				try {
					$channel->wait(null, false, $this->getWaitTimeout($startedAt, $secondsToLive));
				} catch (AMQPTimeoutException) {
					// Timeout only wakes up the loop to check the runtime limit.
				}
			}
		} finally {
			$channel->close();
		}
	}

	private function processMessage(RabbitMQQueueConfig $queue, AMQPChannel $channel, AMQPMessage $message): void
	{
		try {
			$result = $queue->consumer->consume($message->getBody());
		} catch (Throwable $exception) {
			$channel->basic_nack($message->getDeliveryTag(), false, true);

			throw $exception;
		}

		match ($result) {
			RabbitMQConsumeResult::Ack => $channel->basic_ack($message->getDeliveryTag()),
			RabbitMQConsumeResult::Nack => $channel->basic_nack($message->getDeliveryTag(), false, true),
			RabbitMQConsumeResult::Reject => $channel->basic_reject($message->getDeliveryTag(), false),
		};
	}

	private function isTimeLimitReached(int $startedAt, int|null $secondsToLive): bool
	{
		return $secondsToLive !== null && time() - $startedAt >= $secondsToLive;
	}

	private function getWaitTimeout(int $startedAt, int|null $secondsToLive): float
	{
		if ($secondsToLive === null) {
			return 1.0;
		}

		return max(1, $secondsToLive - (time() - $startedAt));
	}

}
