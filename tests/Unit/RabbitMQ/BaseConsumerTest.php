<?php

declare(strict_types = 1);

namespace App\Test\Unit\RabbitMQ;

use App\RabbitMQ\BaseConsumer;
use App\RabbitMQ\RabbitMQConsumeResult;
use App\RabbitMQ\RabbitMQMessage;
use InvalidArgumentException;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class BaseConsumerTest extends TestCase
{

	public function testConsumeMapsPayloadAndReturnsAck(): void
	{
		$messageClass = $this->createMessage('request-1', 123, 'value')::class;
		$consumer = $this->createConsumer($messageClass);

		$result = $consumer->consume(Json::encode([
			'requestId' => 'request-1',
			'messageQueuedAtTimestamp' => 123,
			'value' => 'value',
		]));

		$this->assertSame(RabbitMQConsumeResult::Ack, $result);
		$this->assertInstanceOf($messageClass, $consumer->processedMessage);
		$this->assertSame('request-1', $consumer->processedMessage->getRequestId());
		$this->assertSame(123, $consumer->processedMessage->getMessageQueuedAtTimestamp());
		$this->assertSame('value', $consumer->processedMessage->value);
	}

	public function testConsumeRejectsPayloadThatIsNotJsonObject(): void
	{
		$messageClass = $this->createMessage('request-1', 123, 'value')::class;
		$consumer = $this->createConsumer($messageClass);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('RabbitMQ message payload must be a JSON object.');

		$consumer->consume(Json::encode('invalid'));
	}

	private function createConsumer(string $messageClass): BaseConsumer
	{
		return new class ($messageClass) extends BaseConsumer {

			public RabbitMQMessage|null $processedMessage = null;

			/**
			 * @param class-string<RabbitMQMessage> $messageClass
			 */
			public function __construct(private string $messageClass)
			{
			}

			protected function processMessage(RabbitMQMessage $messageObject): void
			{
				$this->processedMessage = $messageObject;
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
