<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\RabbitMQMessage;

class StockAiAnalysisGeminiProcessMessage implements RabbitMQMessage
{

	public function __construct(
		public string $requestId,
		public int $messageQueuedAtTimestamp,
		public string $runId,
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

}
