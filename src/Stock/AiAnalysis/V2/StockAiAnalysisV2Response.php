<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\V2;

final readonly class StockAiAnalysisV2Response
{

	/**
	 * @param list<StockAiAnalysisV2CompanyAnalysis>|null $portfolioAnalysis
	 * @param list<StockAiAnalysisV2CompanyAnalysis>|null $watchlistAnalysis
	 */
	public function __construct(
		public int $schemaVersion,
		public string $runId,
		public string $analysisAsOf,
		public array|null $portfolioAnalysis = null,
		public array|null $watchlistAnalysis = null,
		public StockAiAnalysisV2CompanyAnalysis|null $stockAnalysis = null,
		public StockAiAnalysisV2MarketOverview|null $marketOverview = null,
		public StockAiAnalysisV2PortfolioEvaluation|null $portfolioEvaluation = null,
		public StockAiAnalysisV2DailyBrief|null $dailyBrief = null,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$data = [
			'schemaVersion' => $this->schemaVersion,
			'runId' => $this->runId,
			'analysisAsOf' => $this->analysisAsOf,
		];

		if ($this->portfolioAnalysis !== null) {
			$data['portfolioAnalysis'] = array_map(
				static fn (StockAiAnalysisV2CompanyAnalysis $item): array => $item->toArray(),
				$this->portfolioAnalysis,
			);
		}

		if ($this->watchlistAnalysis !== null) {
			$data['watchlistAnalysis'] = array_map(
				static fn (StockAiAnalysisV2CompanyAnalysis $item): array => $item->toArray(),
				$this->watchlistAnalysis,
			);
		}

		if ($this->stockAnalysis !== null) {
			$data['stockAnalysis'] = $this->stockAnalysis->toArray();
		}

		foreach (['marketOverview', 'portfolioEvaluation', 'dailyBrief'] as $property) {
			if ($this->{$property} !== null) {
				$data[$property] = $this->{$property}->toArray();
			}
		}

		return $data;
	}

}
