<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2DailyBrief
{

	/**
	 * @param list<array{severity: string, title: string, detail: string, horizon: string}> $alerts
	 * @param list<array{priority: string, action: string, reason: string}> $checklist
	 */
	public function __construct(
		public string $summary,
		public string $marketPulse,
		public string|null $portfolioImpactSummary,
		public string|null $watchlistSummary,
		public array $alerts,
		public array $checklist,
		public string $actionNeeded,
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
			'marketPulse' => $this->marketPulse,
			'portfolioImpactSummary' => $this->portfolioImpactSummary,
			'watchlistSummary' => $this->watchlistSummary,
			'alerts' => $this->alerts,
			'checklist' => $this->checklist,
			'actionNeeded' => $this->actionNeeded,
		];
	}

}
