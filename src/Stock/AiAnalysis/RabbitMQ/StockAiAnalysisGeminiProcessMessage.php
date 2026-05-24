<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\RabbitMQMessage;

class StockAiAnalysisGeminiProcessMessage implements RabbitMQMessage
{

	public const TARGET_RUN = 'run';

	public const TARGET_FOLLOW_UP = 'follow_up';

	public function __construct(
		public string $requestId,
		public int $messageQueuedAtTimestamp,
		public string $runId,
		public string $targetType = self::TARGET_RUN,
		public string|null $followUpQuestionId = null,
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
