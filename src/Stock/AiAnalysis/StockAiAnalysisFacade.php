<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAssetRepository;
use App\Utils\TypeValidator;
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
	)
	{
	}

	public function createRun(
		bool $includesPortfolio,
		bool $includesWatchlist,
		bool $includesMarketOverview,
		string|null $stockTicker = null,
		string|null $stockName = null,
	): StockAiAnalysisRun
	{
		$stockAsset = null;
		if ($stockTicker !== null) {
			$stockAsset = $this->stockAssetRepository->findByTicker($stockTicker);
		}

		$prompt = $this->promptGenerator->generate(
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$stockTicker,
			$stockName,
		);
		$run = new StockAiAnalysisRun(
			$prompt,
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$this->datetimeFactory->createNow(),
			$stockTicker,
			$stockName,
			$stockAsset,
		);

		$this->entityManager->persist($run);
		$this->entityManager->flush();

		return $run;
	}

	public function getRun(string $id): StockAiAnalysisRun
	{
		return $this->stockAiAnalysisRunRepository->getById(Uuid::fromString($id));
	}

	public function processResponse(string $runId, string $rawResponse): void
	{
		$run = $this->getRun($runId);

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

		if (isset($data['marketOverview']) && is_array($data['marketOverview'])) {
			$marketOverview = $data['marketOverview'];
			$marketOverviewSummary = TypeValidator::validateNullableString($marketOverview['summary'] ?? null);
			$sentimentValue = TypeValidator::validateNullableString($marketOverview['sentiment'] ?? null);
			if ($sentimentValue !== null) {
				$marketOverviewSentiment = StockAiAnalysisMarketSentimentEnum::tryFrom($sentimentValue);
			}
		}

		$portfolioEvaluationSummary = null;
		if (isset($data['portfolioEvaluation']) && is_array($data['portfolioEvaluation'])) {
			$portfolioEvaluationSummary = TypeValidator::validateNullableString(
				$data['portfolioEvaluation']['summary'] ?? null,
			);
		}

		$run->setResponse(
			$rawResponse,
			$marketOverviewSummary,
			$marketOverviewSentiment,
			$portfolioEvaluationSummary,
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
			$confidenceLevel,
			$fairPrice,
			$fairPriceCurrency,
			$now,
		);

		$this->entityManager->persist($result);
		$run->addResult($result);
	}

}
