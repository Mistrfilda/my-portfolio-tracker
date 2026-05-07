<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\RabbitMQPublisher;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessMessage;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessProducer;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisGeminiProcessProducerTest extends TestCase
{

	public function testPublishUsesAiClientsQueueAndSerializesMessage(): void
	{
		$publisher = new class implements RabbitMQPublisher {

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
		$producer = new StockAiAnalysisGeminiProcessProducer($publisher, 'aiClientsQueue');

		$producer->publish(new StockAiAnalysisGeminiProcessMessage(
			'request-1',
			123,
			'run-1',
		));

		self::assertSame('aiClientsQueue', $publisher->queueName);
		self::assertSame([
			'requestId' => 'request-1',
			'messageQueuedAtTimestamp' => 123,
			'runId' => 'run-1',
		], Json::decode($publisher->payload, forceArrays: true));
	}

}
