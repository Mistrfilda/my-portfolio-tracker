<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2MarketOverview
{

	/**
	 * @param list<array{title: string, direction: string, impact: string}> $keyDrivers
	 * @param list<array{date: string|null, title: string, relevance: string}> $upcomingEvents
	 * @param list<array{title: string, affectedAreas: list<string>, impact: string}> $geopoliticalRisks
	 */
	public function __construct(
		public string $summary,
		public string $sentiment,
		public array $keyDrivers,
		public array $upcomingEvents,
		public array $geopoliticalRisks,
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
			'sentiment' => $this->sentiment,
			'keyDrivers' => $this->keyDrivers,
			'upcomingEvents' => $this->upcomingEvents,
			'geopoliticalRisks' => $this->geopoliticalRisks,
		];
	}

}
