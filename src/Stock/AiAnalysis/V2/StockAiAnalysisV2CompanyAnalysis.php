<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2CompanyAnalysis
{

	/**
	 * @param array{status: string, issues: list<string>} $dataQuality
	 * @param list<array{date:?string,category:string,sentiment:string,headline:string,impact:string,timeHorizon:string}> $materialEvents
	 * @param array{latestPeriod: string|null, resultVsExpectations: string, nextEarningsDate: string|null, summary: string} $earnings
	 * @param array{status: string, summary: string} $dividend
	 * @param list<array{title: string, horizon: string, rationale: string}> $catalysts
	 * @param list<array{title: string, likelihood: string, impact: string, rationale: string}> $risks
	 * @param array{action: string, confidence: string, reasoning: string, watchConditions: list<string>} $recommendation
	 */
	public function __construct(
		public string|null $stockAssetId,
		public string $stockAssetName,
		public string $stockAssetTicker,
		public string $summary,
		public array $dataQuality,
		public array $materialEvents,
		public array $earnings,
		public array $dividend,
		public array $catalysts,
		public array $risks,
		public StockAiAnalysisV2Valuation $valuation,
		public array $recommendation,
		public string|null $performanceComment = null,
		public string|null $businessSummary = null,
		public string|null $moatAnalysis = null,
		public string|null $financialHealth = null,
		public string|null $conclusion = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$data = [
			'stockAssetId' => $this->stockAssetId,
			'stockAssetName' => $this->stockAssetName,
			'stockAssetTicker' => $this->stockAssetTicker,
			'summary' => $this->summary,
			'dataQuality' => $this->dataQuality,
			'materialEvents' => $this->materialEvents,
			'earnings' => $this->earnings,
			'dividend' => $this->dividend,
			'catalysts' => $this->catalysts,
			'risks' => $this->risks,
			'valuation' => $this->valuation->toArray(),
			'recommendation' => $this->recommendation,
		];
		foreach (['performanceComment', 'businessSummary', 'moatAnalysis', 'financialHealth', 'conclusion'] as $property) {
			if ($this->{$property} !== null) {
				$data[$property] = $this->{$property};
			}
		}

		return $data;
	}

}
