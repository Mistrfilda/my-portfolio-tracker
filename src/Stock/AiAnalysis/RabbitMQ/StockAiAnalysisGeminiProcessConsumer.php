<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\BaseConsumer;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;

/**
 * @extends BaseConsumer<StockAiAnalysisGeminiProcessMessage>
 */
class StockAiAnalysisGeminiProcessConsumer extends BaseConsumer
{

	public function __construct(
		private StockAiAnalysisGeminiProcessorFacade $stockAiAnalysisGeminiProcessorFacade,
	)
	{
	}

	protected function processMessage(object $messageObject): void
	{
		if ($messageObject->targetType === StockAiAnalysisGeminiProcessMessage::TARGET_FOLLOW_UP) {
			$this->stockAiAnalysisGeminiProcessorFacade->processFollowUp($messageObject->followUpQuestionId ?? '');
			return;
		}

		$this->stockAiAnalysisGeminiProcessorFacade->process($messageObject->runId);
	}

	protected function getMessageClass(): string
	{
		return StockAiAnalysisGeminiProcessMessage::class;
	}

}
