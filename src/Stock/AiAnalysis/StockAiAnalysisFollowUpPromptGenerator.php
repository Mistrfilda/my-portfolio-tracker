<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use Nette\Utils\Json;

class StockAiAnalysisFollowUpPromptGenerator
{

	public function generate(StockAiAnalysisRun $run, string $question): string
	{
		$context = [
			'analysisMetadata' => [
				'includesPortfolio' => $run->includesPortfolio(),
				'includesWatchlist' => $run->includesWatchlist(),
				'includesMarketOverview' => $run->includesMarketOverview(),
				'portfolioPromptType' => $run->getPortfolioPromptType()?->value,
				'isDailyBrief' => $run->isDailyBrief(),
				'stockTicker' => $run->getStockTicker(),
				'stockName' => $run->getStockName(),
			],
			'originalGeneratedPrompt' => $run->getGeneratedPrompt(),
			'storedSummaries' => $this->buildStoredSummaries($run),
			'stockResults' => $this->buildStockResults($run),
		];

		if ($run->getRawResponse() !== null) {
			$context['rawResponse'] = $run->getRawResponse();
		}

		return implode("\n\n", [
			'Navazuješ na již hotovou investiční AI analýzu. Odpověz pouze na doplňující dotaz uživatele '
				. 'a používej přiložený kontext původní analýzy.',
			'Pokud kontext nestačí k jednoznačnému závěru, jasně uveď, co chybí. Nevracej JSON, odpověz běžným textem.',
			'Kontext původní analýzy:',
			Json::encode($context, pretty: true),
			'Doplňující dotaz uživatele:',
			$question,
		]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildStoredSummaries(StockAiAnalysisRun $run): array
	{
		return array_filter([
			'marketOverviewSummary' => $run->getMarketOverviewSummary(),
			'marketOverviewSentiment' => $run->getMarketOverviewSentiment()?->value,
			'marketOverviewGeopoliticalContext' => $run->getMarketOverviewGeopoliticalContext(),
			'portfolioEvaluationSummary' => $run->getPortfolioEvaluationSummary(),
			'portfolioPerformance7DaysSummary' => $run->getPortfolioPerformance7DaysSummary(),
			'dailyBriefSummary' => $run->getDailyBriefSummary(),
			'dailyBriefMarketPulse' => $run->getDailyBriefMarketPulse(),
			'dailyBriefPortfolioImpactSummary' => $run->getDailyBriefPortfolioImpactSummary(),
			'dailyBriefWatchlistSummary' => $run->getDailyBriefWatchlistSummary(),
			'dailyBriefImportantAlerts' => $run->getDailyBriefImportantAlerts(),
			'dailyBriefNextDaysChecklist' => $run->getDailyBriefNextDaysChecklist(),
			'dailyBriefActionNeeded' => $run->getDailyBriefActionNeeded()?->value,
		], static fn (mixed $value): bool => $value !== null);
	}

	/**
	 * @return array<array<string, mixed>>
	 */
	private function buildStockResults(StockAiAnalysisRun $run): array
	{
		$stockResults = [];
		foreach ($run->getResults() as $result) {
			$stockResults[] = array_filter([
				'type' => $result->getType()->value,
				'stockTicker' => $result->getStockTicker(),
				'stockName' => $result->getStockName(),
				'positiveNews' => $result->getPositiveNews(),
				'negativeNews' => $result->getNegativeNews(),
				'interestingNews' => $result->getInterestingNews(),
				'aiOpinion' => $result->getAiOpinion(),
				'actionSuggestion' => $result->getActionSuggestion()?->value,
				'reasoning' => $result->getReasoning(),
				'news' => $result->getNews(),
				'businessSummary' => $result->getBusinessSummary(),
				'moatAnalysis' => $result->getMoatAnalysis(),
				'financialHealth' => $result->getFinancialHealth(),
				'growthCatalysts' => $result->getGrowthCatalysts(),
				'valuationAssessment' => $result->getValuationAssessment(),
				'conclusion' => $result->getConclusion(),
				'risks' => $result->getRisks(),
				'earningsCommentary' => $result->getEarningsCommentary(),
				'dividendAnalysis' => $result->getDividendAnalysis(),
				'performance7DaysComment' => $result->getPerformance7DaysComment(),
				'performance1DayComment' => $result->getPerformance1DayComment(),
				'confidenceLevel' => $result->getConfidenceLevel()?->value,
				'fairPrice' => $result->getFairPrice(),
				'fairPriceCurrency' => $result->getFairPriceCurrency()?->value,
			], static fn (mixed $value): bool => $value !== null);
		}

		return $stockResults;
	}

}
