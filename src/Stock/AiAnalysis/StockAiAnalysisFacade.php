<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessMessage;
use App\Stock\AiAnalysis\RabbitMQ\StockAiAnalysisGeminiProcessProducer;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2Response;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SnapshotFactory;
use App\Stock\Asset\StockAssetRepository;
use App\Utils\TypeValidator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisFacade
{

	public function __construct(
		private StockAiAnalysisPromptGenerator $promptGenerator,
		private StockAiAnalysisRunRepository $stockAiAnalysisRunRepository,
		private StockAssetRepository $stockAssetRepository,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private StockAiAnalysisGeminiProcessProducer $stockAiAnalysisGeminiProcessProducer,
		private StockAiAnalysisV2SnapshotFactory $v2SnapshotFactory,
		private StockAiAnalysisV2PromptGenerator $v2PromptGenerator,
		private StockAiAnalysisV2ResponseValidator $v2ResponseValidator,
	)
	{
	}

	public function createRun(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		StockAiAnalysisPortfolioPromptTypeEnum|null $portfolioPromptType = null,
		string|null $stockTicker = null,
		string|null $stockName = null,
	): StockAiAnalysisRun
	{
		$stockAsset = null;
		if ($stockTicker !== null) {
			$stockAsset = $this->stockAssetRepository->findByTicker($stockTicker);
		}

		$now = $this->datetimeFactory->createNow();
		$runId = Uuid::uuid4();
		$snapshot = $this->v2SnapshotFactory->create(
			$runId,
			$now,
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$portfolioPromptType,
			$stockTicker,
			$stockName,
			$stockAsset,
		);
		$prompt = $this->v2PromptGenerator->generateManualPrompt($snapshot);
		$run = new StockAiAnalysisRun(
			$prompt,
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$portfolioPromptType,
			$now,
			$stockTicker,
			$stockName,
			$stockAsset,
			2,
			$snapshot,
			$runId,
		);

		$this->entityManager->persist($run);
		$this->entityManager->flush();

		return $run;
	}

	public function getRun(string $id): StockAiAnalysisRun
	{
		return $this->stockAiAnalysisRunRepository->getById(Uuid::fromString($id));
	}

	public function getGeneratedPromptForDisplay(StockAiAnalysisRun $run): string
	{
		if ($run->isV2() && $run->getInputSnapshot() !== null) {
			return sprintf(
				"Systémový prompt:\n\n%s\n\nUživatelský prompt:\n\n%s",
				$this->v2PromptGenerator->generateSystemInstruction($run->getInputSnapshot()),
				$run->getGeneratedPrompt(),
			);
		}

		return sprintf(
			"Systémový prompt:\n\n%s\n\nUživatelský prompt:\n\n%s",
			$this->promptGenerator->generateSystemInstruction(),
			$run->getGeneratedPrompt(),
		);
	}

	public function getManualOpenPositionsPrompt(): string
	{
		return $this->promptGenerator->generateManualOpenPositionsPrompt();
	}

	public function enqueueGeminiProcessing(string $runId): void
	{
		$run = $this->getRun($runId);
		if (!$run->canBeQueuedForGeminiProcessing()) {
			return;
		}

		$now = $this->datetimeFactory->createNow();
		$run->markGeminiQueued($now);
		$this->entityManager->flush();

		$this->stockAiAnalysisGeminiProcessProducer->publish(new StockAiAnalysisGeminiProcessMessage(
			Uuid::uuid4()->toString(),
			$now->getTimestamp(),
			$runId,
		));
	}

	public function processResponse(
		string $runId,
		string $rawResponse,
		StockAiAnalysisProcessingSourceEnum $processingSource = StockAiAnalysisProcessingSourceEnum::MANUAL,
	): void
	{
		$run = $this->getRun($runId);
		if ($run->isV2()) {
			$this->processV2Response($run, $rawResponse, $processingSource);
			return;
		}

		try {
			$data = Json::decode($rawResponse, forceArrays: true);
		} catch (JsonException) {
			throw new Exception('Invalid JSON response');
		}

		if (!is_array($data)) {
			throw new Exception('Response is not a valid JSON object');
		}

		$now = $this->datetimeFactory->createNow();

		$marketOverviewSummary = null;
		$marketOverviewSentiment = null;
		$marketOverviewGeopoliticalContext = null;

		if (isset($data['marketOverview']) && is_array($data['marketOverview'])) {
			$marketOverview = $data['marketOverview'];
			$marketOverviewSummary = TypeValidator::validateNullableString($marketOverview['summary'] ?? null);
			$sentimentValue = TypeValidator::validateNullableString($marketOverview['sentiment'] ?? null);
			if ($sentimentValue !== null) {
				$marketOverviewSentiment = StockAiAnalysisMarketSentimentEnum::tryFrom($sentimentValue);
			}

			$marketOverviewGeopoliticalContext = TypeValidator::validateNullableString(
				$marketOverview['geopoliticalContext'] ?? null,
			);
		}

		$portfolioEvaluationSummary = null;
		$portfolioPerformance7DaysSummary = null;
		if (isset($data['portfolioEvaluation']) && is_array($data['portfolioEvaluation'])) {
			$portfolioEvaluationSummary = TypeValidator::validateNullableString(
				$data['portfolioEvaluation']['summary'] ?? null,
			);
			$portfolioPerformance7DaysSummary = TypeValidator::validateNullableString(
				$data['portfolioEvaluation']['performance7DaysSummary'] ?? null,
			);
		}

		$dailyBriefSummary = null;
		$dailyBriefMarketPulse = null;
		$dailyBriefPortfolioImpactSummary = null;
		$dailyBriefWatchlistSummary = null;
		$dailyBriefImportantAlerts = null;
		$dailyBriefNextDaysChecklist = null;
		$dailyBriefActionNeeded = null;
		if (isset($data['dailyBrief']) && is_array($data['dailyBrief'])) {
			$dailyBriefSummary = TypeValidator::validateNullableString($data['dailyBrief']['summary'] ?? null);
			$dailyBriefMarketPulse = TypeValidator::validateNullableString($data['dailyBrief']['marketPulse'] ?? null);
			$dailyBriefPortfolioImpactSummary = TypeValidator::validateNullableString(
				$data['dailyBrief']['portfolioImpactSummary'] ?? null,
			);
			$dailyBriefWatchlistSummary = TypeValidator::validateNullableString(
				$data['dailyBrief']['watchlistSummary'] ?? null,
			);
			$dailyBriefImportantAlerts = TypeValidator::validateNullableString(
				$data['dailyBrief']['importantAlerts'] ?? null,
			);
			$dailyBriefNextDaysChecklist = TypeValidator::validateNullableString(
				$data['dailyBrief']['nextDaysChecklist'] ?? null,
			);
			$actionNeededValue = TypeValidator::validateNullableString($data['dailyBrief']['actionNeeded'] ?? null);
			if ($actionNeededValue !== null) {
				$dailyBriefActionNeeded = StockAiAnalysisDailyBriefActionNeededEnum::tryFrom($actionNeededValue);
			}
		}

		$run->setResponse(
			$rawResponse,
			$marketOverviewSummary,
			$marketOverviewSentiment,
			$marketOverviewGeopoliticalContext,
			$portfolioEvaluationSummary,
			$portfolioPerformance7DaysSummary,
			$dailyBriefSummary,
			$dailyBriefMarketPulse,
			$dailyBriefPortfolioImpactSummary,
			$dailyBriefWatchlistSummary,
			$dailyBriefImportantAlerts,
			$dailyBriefNextDaysChecklist,
			$dailyBriefActionNeeded,
			$now,
		);

		if (isset($data['portfolioAnalysis']) && is_array($data['portfolioAnalysis'])) {
			foreach ($data['portfolioAnalysis'] as $analysis) {
				if (is_array($analysis)) {
					$this->processStockAnalysis($run, $analysis, StockAiAnalysisResultTypeEnum::PORTFOLIO, $now);
				}
			}
		}

		if (isset($data['watchlistAnalysis']) && is_array($data['watchlistAnalysis'])) {
			foreach ($data['watchlistAnalysis'] as $analysis) {
				if (is_array($analysis)) {
					$this->processStockAnalysis($run, $analysis, StockAiAnalysisResultTypeEnum::WATCHLIST, $now);
				}
			}
		}

		if (isset($data['stockAnalysis']) && is_array($data['stockAnalysis'])) {
			$this->processSingleStockAnalysis($run, $data['stockAnalysis'], $now);
		}

		$this->entityManager->flush();
	}

	private function processV2Response(
		StockAiAnalysisRun $run,
		string $rawResponse,
		StockAiAnalysisProcessingSourceEnum $processingSource,
	): void
	{
		$snapshot = $run->getInputSnapshot();
		if ($snapshot === null) {
			throw new Exception('V2 analysis run is missing its input snapshot.');
		}

		$response = $this->v2ResponseValidator->validate($rawResponse, $snapshot);
		$runId = $run->getId();
		$this->entityManager->wrapInTransaction(function () use (
			$runId,
			$rawResponse,
			$processingSource,
			$response,
			$snapshot,
		): void {
			$lockedRun = $this->stockAiAnalysisRunRepository->getById(
				$runId,
				LockMode::PESSIMISTIC_WRITE,
			);
			if ($lockedRun->getProcessedAt() !== null) {
				throw new Exception('AI analysis run has already been processed.');
			}

			$now = $this->datetimeFactory->createNow();
			$this->persistV2StockResults($lockedRun, $response, $snapshot, $now);
			$lockedRun->setV2Response(
				$rawResponse,
				$this->getV2RunStructuredData($response),
				$processingSource,
				$now,
			);
		});
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function persistV2StockResults(
		StockAiAnalysisRun $run,
		StockAiAnalysisV2Response $response,
		array $snapshot,
		ImmutableDateTime $now,
	): void
	{
		foreach ($response->portfolioAnalysis ?? [] as $analysis) {
			$analysisData = $analysis->toArray();
			$this->persistV2StockResult(
				$run,
				$analysisData,
				StockAiAnalysisResultTypeEnum::PORTFOLIO,
				$this->findSnapshotItem($snapshot, 'portfolio', $analysisData),
				$now,
			);
		}

		foreach ($response->watchlistAnalysis ?? [] as $analysis) {
			$analysisData = $analysis->toArray();
			$this->persistV2StockResult(
				$run,
				$analysisData,
				StockAiAnalysisResultTypeEnum::WATCHLIST,
				$this->findSnapshotItem($snapshot, 'watchlist', $analysisData),
				$now,
			);
		}

		if ($response->stockAnalysis !== null) {
			$singleStockSnapshot = is_array($snapshot['singleStock'] ?? null)
				? $this->normalizeObject($snapshot['singleStock'])
				: [];
			$this->persistV2StockResult(
				$run,
				$response->stockAnalysis->toArray(),
				StockAiAnalysisResultTypeEnum::SINGLE_STOCK,
				$singleStockSnapshot,
				$now,
			);
		}
	}

	/**
	 * @param array<string, mixed> $analysis
	 * @param array<string, mixed> $snapshotItem
	 */
	private function persistV2StockResult(
		StockAiAnalysisRun $run,
		array $analysis,
		StockAiAnalysisResultTypeEnum $type,
		array $snapshotItem,
		ImmutableDateTime $now,
	): void
	{
		$stockAsset = $type === StockAiAnalysisResultTypeEnum::SINGLE_STOCK
			? $run->getStockAsset()
			: $this->stockAssetRepository->getById(Uuid::fromString(
				TypeValidator::validateString($analysis['stockAssetId'] ?? null),
			));
		$recommendation = TypeValidator::validateArray($analysis['recommendation'] ?? null);
		$valuation = TypeValidator::validateArray($analysis['valuation'] ?? null);
		$fairPrice = TypeValidator::validateNullableFloat($valuation['fairValueBase'] ?? null);
		$currencyValue = TypeValidator::validateNullableString($valuation['currency'] ?? null);
		$structuredData = $analysis;
		$structuredData['marginOfSafetyPercent'] = $this->calculateMarginOfSafety(
			TypeValidator::validateNullableFloat($snapshotItem['currentPrice'] ?? null),
			$fairPrice,
		);

		$result = new StockAiAnalysisStockResult(
			$run,
			$stockAsset,
			$type,
			null,
			null,
			null,
			null,
			StockAiAnalysisActionSuggestionEnum::from(
				TypeValidator::validateString($recommendation['action'] ?? null),
			),
			TypeValidator::validateString($recommendation['reasoning'] ?? null),
			null,
			TypeValidator::validateString($analysis['stockAssetTicker'] ?? null),
			TypeValidator::validateString($analysis['stockAssetName'] ?? null),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			StockAiAnalysisConfidenceLevelEnum::from(
				TypeValidator::validateString($recommendation['confidence'] ?? null),
			),
			$fairPrice,
			$currencyValue !== null ? CurrencyEnum::from($currencyValue) : null,
			$now,
			$structuredData,
		);
		$run->addResult($result);
		$this->entityManager->persist($result);
	}

	private function calculateMarginOfSafety(float|null $currentPrice, float|null $fairValueBase): float|null
	{
		if ($currentPrice === null || $fairValueBase === null || $fairValueBase <= 0) {
			return null;
		}

		return round(($fairValueBase - $currentPrice) / $fairValueBase * 100, 2);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @param array<string, mixed> $analysis
	 * @return array<string, mixed>
	 */
	private function findSnapshotItem(array $snapshot, string $key, array $analysis): array
	{
		$items = is_array($snapshot[$key] ?? null) ? $snapshot[$key] : [];
		foreach ($items as $item) {
			if (
				is_array($item)
				&& ($item['stockAssetId'] ?? null) === ($analysis['stockAssetId'] ?? null)
			) {
				return $this->normalizeObject($item);
			}
		}

		throw new Exception('Analysis item is missing from the immutable input snapshot.');
	}

	/**
	 * @param array<mixed> $data
	 * @return array<string, mixed>
	 */
	private function normalizeObject(array $data): array
	{
		$result = [];
		foreach ($data as $key => $value) {
			if (!is_string($key)) {
				throw new Exception('Expected an object with string keys in the immutable input snapshot.');
			}

			$result[$key] = $value;
		}

		return $result;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getV2RunStructuredData(StockAiAnalysisV2Response $response): array
	{
		$data = [
			'schemaVersion' => $response->schemaVersion,
			'runId' => $response->runId,
			'analysisAsOf' => $response->analysisAsOf,
		];
		foreach (['marketOverview', 'portfolioEvaluation', 'dailyBrief'] as $property) {
			if ($response->{$property} !== null) {
				$data[$property] = $response->{$property}->toArray();
			}
		}

		return $data;
	}

	/**
	 * @param array<mixed> $data
	 */
	private function processStockAnalysis(
		StockAiAnalysisRun $run,
		array $data,
		StockAiAnalysisResultTypeEnum $type,
		ImmutableDateTime $now,
	): void
	{
		$stockAssetId = TypeValidator::validateNullableString($data['stockAssetId'] ?? null);
		if ($stockAssetId === null) {
			return;
		}

		try {
			$stockAsset = $this->stockAssetRepository->getById(Uuid::fromString($stockAssetId));
		} catch (NoEntityFoundException) {
			return;
		}

		$actionSuggestion = null;
		if ($type === StockAiAnalysisResultTypeEnum::PORTFOLIO) {
			$actionSuggestionValue = TypeValidator::validateNullableString($data['actionSuggestion'] ?? null);
		} else {
			$actionSuggestionValue = TypeValidator::validateNullableString($data['buyRecommendation'] ?? null);
		}

		if ($actionSuggestionValue !== null) {
			$actionSuggestion = StockAiAnalysisActionSuggestionEnum::tryFrom($actionSuggestionValue);
		}

		$fairPrice = null;
		if (isset($data['fairPrice']) && (is_float($data['fairPrice']) || is_int($data['fairPrice']))) {
			$fairPrice = (float) $data['fairPrice'];
		}

		$fairPriceCurrency = null;
		$fairPriceCurrencyValue = TypeValidator::validateNullableString($data['fairPriceCurrency'] ?? null);
		if ($fairPriceCurrencyValue !== null) {
			$fairPriceCurrency = CurrencyEnum::tryFrom($fairPriceCurrencyValue);
		}

		$confidenceLevel = null;
		$confidenceLevelValue = TypeValidator::validateNullableString($data['confidenceLevel'] ?? null);
		if ($confidenceLevelValue !== null) {
			$confidenceLevel = StockAiAnalysisConfidenceLevelEnum::tryFrom($confidenceLevelValue);
		}

		$result = new StockAiAnalysisStockResult(
			$run,
			$stockAsset,
			$type,
			TypeValidator::validateNullableString($data['positiveNews'] ?? null),
			TypeValidator::validateNullableString($data['negativeNews'] ?? null),
			TypeValidator::validateNullableString($data['interestingNews'] ?? null),
			TypeValidator::validateNullableString($data['aiOpinion'] ?? null),
			$actionSuggestion,
			TypeValidator::validateNullableString($data['reasoning'] ?? null),
			TypeValidator::validateNullableString($data['news'] ?? null),
			TypeValidator::validateNullableString($data['stockAssetTicker'] ?? null),
			TypeValidator::validateNullableString($data['stockAssetName'] ?? null),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			TypeValidator::validateNullableString($data['earningsCommentary'] ?? null),
			TypeValidator::validateNullableString($data['dividendAnalysis'] ?? null),
			TypeValidator::validateNullableString($data['performance7DaysComment'] ?? null),
			TypeValidator::validateNullableString($data['performance1DayComment'] ?? null),
			$confidenceLevel,
			$fairPrice,
			$fairPriceCurrency,
			$now,
		);

		$this->entityManager->persist($result);
		$run->addResult($result);
	}

	/**
	 * @param array<mixed> $data
	 */
	private function processSingleStockAnalysis(
		StockAiAnalysisRun $run,
		array $data,
		ImmutableDateTime $now,
	): void
	{
		$stockAsset = $run->getStockAsset();
		if ($stockAsset === null && $run->getStockTicker() !== null) {
			$stockAsset = $this->stockAssetRepository->findByTicker($run->getStockTicker());
		}

		$actionSuggestionValue = TypeValidator::validateNullableString($data['recommendation'] ?? null);
		$actionSuggestion = null;

		if ($actionSuggestionValue !== null) {
			$actionSuggestion = StockAiAnalysisActionSuggestionEnum::tryFrom($actionSuggestionValue);
		}

		$fairPrice = null;
		if (isset($data['fairPrice']) && (is_float($data['fairPrice']) || is_int($data['fairPrice']))) {
			$fairPrice = (float) $data['fairPrice'];
		}

		$fairPriceCurrency = null;
		$fairPriceCurrencyValue = TypeValidator::validateNullableString($data['fairPriceCurrency'] ?? null);
		if ($fairPriceCurrencyValue !== null) {
			$fairPriceCurrency = CurrencyEnum::tryFrom($fairPriceCurrencyValue);
		}

		$confidenceLevel = null;
		$confidenceLevelValue = TypeValidator::validateNullableString($data['confidenceLevel'] ?? null);
		if ($confidenceLevelValue !== null) {
			$confidenceLevel = StockAiAnalysisConfidenceLevelEnum::tryFrom($confidenceLevelValue);
		}

		$result = new StockAiAnalysisStockResult(
			$run,
			$stockAsset,
			StockAiAnalysisResultTypeEnum::SINGLE_STOCK,
			null,
			null,
			null,
			null,
			$actionSuggestion,
			null,
			null,
			$run->getStockTicker(),
			$run->getStockName(),
			TypeValidator::validateNullableString($data['businessSummary'] ?? null),
			TypeValidator::validateNullableString($data['moatAnalysis'] ?? null),
			TypeValidator::validateNullableString($data['financialHealth'] ?? null),
			TypeValidator::validateNullableString($data['growthCatalysts'] ?? null),
			TypeValidator::validateNullableString($data['valuationAssessment'] ?? null),
			TypeValidator::validateNullableString($data['conclusion'] ?? null),
			TypeValidator::validateNullableString($data['risks'] ?? null),
			TypeValidator::validateNullableString($data['earningsCommentary'] ?? null),
			TypeValidator::validateNullableString($data['dividendAnalysis'] ?? null),
			null,
			null,
			$confidenceLevel,
			$fairPrice,
			$fairPriceCurrency,
			$now,
		);

		$this->entityManager->persist($result);
		$run->addResult($result);
	}

}
