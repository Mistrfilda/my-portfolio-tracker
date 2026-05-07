<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\RabbitMQConsumeResult;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessConsumer;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisGeminiProcessConsumerTest extends TestCase
{

	public function testConsumeProcessesStockAiAnalysisRun(): void
	{
		$processorFacade = $this->createMock(StockAiAnalysisGeminiProcessorFacade::class);
		$processorFacade->expects(self::once())
			->method('process')
			->with('run-1');

		$consumer = new StockAiAnalysisGeminiProcessConsumer($processorFacade);

		$result = $consumer->consume(Json::encode([
			'requestId' => 'request-1',
			'messageQueuedAtTimestamp' => 123,
			'runId' => 'run-1',
		]));

		self::assertSame(RabbitMQConsumeResult::Ack, $result);
	}

}
