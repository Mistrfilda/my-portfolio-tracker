<?php

declare(strict_types = 1);

namespace App\Mcp\Tool;

use App\Admin\CurrentAppAdminGetter;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistItem;
use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistProvider;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class AiAnalysesTool
{

	private const int MAX_LIMIT = 20;

	private const array ALLOWED_TYPES = ['portfolio', 'watchlist', 'market', 'daily_brief', 'single_stock'];

	public function __construct(
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly StockAssetResolver $stockAssetResolver,
		private readonly StockAiAnalysisRunRepository $runRepository,
		private readonly StockAiAnalysisStockResultRepository $stockResultRepository,
		private readonly StockAiAnalysisActionChecklistProvider $checklistProvider,
	)
	{
	}

	/**
	 * @return array<string, mixed>
	 */
	public function execute(
		string|null $ticker = null,
		string|null $stockAssetId = null,
		string|null $type = null,
		int $limit = 5,
	): array
	{
		if ($limit < 1 || $limit > self::MAX_LIMIT) {
			throw new McpInvalidInputException(sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
		}

		$normalizedType = $type !== null && trim($type) !== '' ? strtolower(trim($type)) : null;
		if ($normalizedType !== null && !in_array($normalizedType, self::ALLOWED_TYPES, true)) {
			throw new McpInvalidInputException(
				sprintf(
					'Unsupported analysis type "%s". Allowed values: %s.',
					$type ?? '',
					implode(', ', self::ALLOWED_TYPES),
				),
			);
		}

		$owner = $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		$hasStockFilter = ($ticker !== null && trim($ticker) !== '')
			|| ($stockAssetId !== null && trim($stockAssetId) !== '');

		$stockInfo = null;
		$serializedStockResults = [];

		if ($hasStockFilter) {
			$asset = $this->stockAssetResolver->resolve($ticker, $stockAssetId);
			$stockInfo = [
				'stockAssetId' => $asset->getId()->toString(),
				'ticker' => $asset->getTicker(),
				'name' => $asset->getName(),
			];

			$stockResults = $this->stockResultRepository->findLatestForStockAsset($asset, $limit, $owner);
			$serializedStockResults = array_map($this->serializeStockResult(...), $stockResults);

			if ($normalizedType === null) {
				/** @var array<string, StockAiAnalysisRun> $runsById */
				$runsById = [];
				foreach ($stockResults as $result) {
					$run = $result->getStockAiAnalysisRun();
					$runsById[$run->getId()->toString()] = $run;
				}

				$runs = array_values($runsById);
			} else {
				$runs = $this->runRepository->findLatestForAppAdmin($owner, $normalizedType, $limit);
			}
		} else {
			$runs = $this->runRepository->findLatestForAppAdmin($owner, $normalizedType, $limit);
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'filter' => [
				'stock' => $stockInfo,
				'type' => $normalizedType,
				'limit' => $limit,
			],
			'runsCount' => count($runs),
			'runs' => array_map($this->serializeRun(...), $runs),
			'stockResultsCount' => count($serializedStockResults),
			'stockResults' => $serializedStockResults,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeRun(StockAiAnalysisRun $run): array
	{
		$checklist = array_map(
			static fn (StockAiAnalysisActionChecklistItem $item): array => [
				'text' => $item->getText(),
				'priority' => $item->getPriority()->value,
			],
			$this->checklistProvider->getForRun($run),
		);

		$base = [
			'runId' => $run->getId()->toString(),
			'createdAt' => $run->getCreatedAt()->format(DATE_ATOM),
			'processedAt' => $run->getProcessedAt()?->format(DATE_ATOM),
			'schemaVersion' => $run->getAnalysisSchemaVersion(),
			'processingSource' => $run->getProcessingSource()?->value,
			'includesPortfolio' => $run->includesPortfolio(),
			'includesWatchlist' => $run->includesWatchlist(),
			'includesMarketOverview' => $run->includesMarketOverview(),
			'portfolioPromptType' => $run->getPortfolioPromptType()?->value,
			'stockTicker' => $run->getStockTicker(),
			'stockName' => $run->getStockName(),
			'actionChecklist' => $checklist,
		];

		if ($run->isV2()) {
			$base['summary'] = $run->getStructuredData();
		} else {
			$base['summary'] = [
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
				'dailyBriefActionNeeded' => $run->getDailyBriefActionNeeded()?->value,
			];
		}

		return $base;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeStockResult(StockAiAnalysisStockResult $result): array
	{
		$run = $result->getStockAiAnalysisRun();

		$data = [
			'resultId' => $result->getId()->toString(),
			'runId' => $run->getId()->toString(),
			'runCreatedAt' => $run->getCreatedAt()->format(DATE_ATOM),
			'runProcessedAt' => $run->getProcessedAt()?->format(DATE_ATOM),
			'schemaVersion' => $run->getAnalysisSchemaVersion(),
			'processingSource' => $run->getProcessingSource()?->value,
			'resultType' => $result->getType()->value,
			'ticker' => $result->getStockAsset()?->getTicker() ?? $result->getStockTicker(),
			'name' => $result->getStockAsset()?->getName() ?? $result->getStockName(),
			'actionSuggestion' => $result->getActionSuggestion()?->value,
			'confidenceLevel' => $result->getConfidenceLevel()?->value,
			'fairPrice' => $result->getFairPrice(),
			'fairPriceCurrency' => $result->getFairPriceCurrency()?->value,
			'v2FairValueLow' => $result->getV2FairValueLow(),
			'v2FairValueHigh' => $result->getV2FairValueHigh(),
			'v2MarginOfSafetyPercent' => $result->getV2MarginOfSafetyPercent(),
		];

		if ($run->isV2() && $result->getStructuredData() !== null) {
			$data['structuredData'] = $result->getStructuredData();
		} else {
			$data['analysis'] = [
				'aiOpinion' => $result->getAiOpinion(),
				'reasoning' => $result->getReasoning(),
				'valuationAssessment' => $result->getValuationAssessment(),
				'businessSummary' => $result->getBusinessSummary(),
				'moatAnalysis' => $result->getMoatAnalysis(),
				'financialHealth' => $result->getFinancialHealth(),
				'growthCatalysts' => $result->getGrowthCatalysts(),
				'risks' => $result->getRisks(),
				'dividendAnalysis' => $result->getDividendAnalysis(),
				'earningsCommentary' => $result->getEarningsCommentary(),
				'positiveNews' => $result->getPositiveNews(),
				'negativeNews' => $result->getNegativeNews(),
				'conclusion' => $result->getConclusion(),
			];
		}

		return $data;
	}

}
