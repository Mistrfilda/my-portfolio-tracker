<?php

declare(strict_types = 1);

namespace App\Test\Unit\RabbitMQ;

use App\RabbitMQ\BaseProducer;
use App\RabbitMQ\RabbitMQMessage;
use App\RabbitMQ\RabbitMQPublisher;
use InvalidArgumentException;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class BaseProducerTest extends TestCase
{

	public function testPublishSerializesMessageAndUsesConfiguredQueue(): void
	{
		$publisher = $this->createPublisher();
		$message = $this->createMessage('request-1', 123, 'value');
		$producer = $this->createProducer($publisher, $message::class);

		$producer->publish($message);

		$this->assertSame('testQueue', $publisher->queueName);
		$this->assertSame([
			'requestId' => 'request-1',
			'messageQueuedAtTimestamp' => 123,
			'value' => 'value',
		], Json::decode($publisher->payload, forceArrays: true));
	}

	public function testPublishRejectsDifferentMessageType(): void
	{
		$message = $this->createMessage('request-1', 123, 'value');
		$producer = $this->createProducer($this->createPublisher(), $message::class);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Message must be an instance of ' . $message::class);

		$producer->publish(new class implements RabbitMQMessage {

			public function getRequestId(): string
			{
				return 'request-1';
			}

			public function getMessageQueuedAtTimestamp(): int
			{
				return 123;
			}

		});
	}

	private function createPublisher(): RabbitMQPublisher
	{
		return new class implements RabbitMQPublisher {

			public string|null $queueName = null;

			public string|null $payload = null;

			/**
			 * @param array<string, mixed> $headers
			 */
			public function publish(string $queueName, string $payload, array $headers = []): void
			{
				$this->queueName = $queueName;
				$this->payload = $payload;
			}

		};
	}

	private function createProducer(RabbitMQPublisher $publisher, string $messageClass): BaseProducer
	{
		return new class ($publisher, 'testQueue', $messageClass) extends BaseProducer {

			/**
			 * @param class-string<RabbitMQMessage> $messageClass
			 */
			public function __construct(
				RabbitMQPublisher $publisher,
				string $queueName,
				private string $messageClass,
			)
			{
				parent::__construct($publisher, $queueName);
			}

			protected function getMessageClass(): string
			{
				return $this->messageClass;
			}

		};
	}

	private function createMessage(string $requestId, int $messageQueuedAtTimestamp, string $value): RabbitMQMessage
	{
		return new class ($requestId, $messageQueuedAtTimestamp, $value) implements RabbitMQMessage {

			public function __construct(
				private string $requestId,
				private int $messageQueuedAtTimestamp,
				public string $value,
			)
			{
			}

			public function getRequestId(): string
			{
				return $this->requestId;
			}

			public function getMessageQueuedAtTimestamp(): int
			{
				return $this->messageQueuedAtTimestamp;
			}

		};
	}

}
