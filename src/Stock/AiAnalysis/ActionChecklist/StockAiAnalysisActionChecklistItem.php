<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\ActionChecklist;

class StockAiAnalysisActionChecklistItem
{

	public function __construct(
		private string $text,
		private StockAiAnalysisActionChecklistPriorityEnum $priority,
	)
	{
	}

	public function getText(): string
	{
		return $this->text;
	}

	public function getPriority(): StockAiAnalysisActionChecklistPriorityEnum
	{
		return $this->priority;
	}

}
