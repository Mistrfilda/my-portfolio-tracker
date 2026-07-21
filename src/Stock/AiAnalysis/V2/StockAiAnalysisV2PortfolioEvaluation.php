<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2PortfolioEvaluation
{

	/**
	 * @param list<array{sector: string, allocationPercent: float, assessment: string}> $concentrationRisks
	 * @param list<array{priority: string, action: string, rationale: string}> $actions
	 */
	public function __construct(
		public string $summary,
		public string $performance7DaysSummary,
		public array $concentrationRisks,
		public array $actions,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'summary' => $this->summary,
			'performance7DaysSummary' => $this->performance7DaysSummary,
			'concentrationRisks' => $this->concentrationRisks,
			'actions' => $this->actions,
		];
	}

}
