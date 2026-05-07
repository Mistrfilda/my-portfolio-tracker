<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\RabbitMQ;

use App\RabbitMQ\BaseProducer;

/**
 * @extends BaseProducer<StockAiAnalysisGeminiProcessMessage>
 */
class StockAiAnalysisGeminiProcessProducer extends BaseProducer
{

	protected function getMessageClass(): string
	{
		return StockAiAnalysisGeminiProcessMessage::class;
	}

}
