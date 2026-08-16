<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

final readonly class StockAiInvestmentPlanResponse
{

	/**
	 * @param array<string, mixed> $decision
	 * @param list<array<string, mixed>> $allocations
	 * @param array<string, mixed> $portfolioImpact
	 * @param list<array<string, mixed>> $alternatives
	 * @param list<string> $keyRisks
	 */
	public function __construct(
		public int $schemaVersion,
		public string $planId,
		public string $analysisAsOf,
		public array $decision,
		public array $allocations,
		public array $portfolioImpact,
		public array $alternatives,
		public array $keyRisks,
	)
	{
	}

	/** @return array<string, mixed> */
	public function toArray(): array
	{
		return [
			'schemaVersion' => $this->schemaVersion,
			'planId' => $this->planId,
			'analysisAsOf' => $this->analysisAsOf,
			'decision' => $this->decision,
			'allocations' => $this->allocations,
			'portfolioImpact' => $this->portfolioImpact,
			'alternatives' => $this->alternatives,
			'keyRisks' => $this->keyRisks,
		];
	}

}
