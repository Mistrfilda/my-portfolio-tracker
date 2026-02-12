<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

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
	): StockAiAnalysisRun
	{
		$prompt = $this->promptGenerator->generate($includesPortfolio, $includesWatchlist, $includesMarketOverview);
		$run = new StockAiAnalysisRun(
			$prompt,
			$includesPortfolio,
			$includesWatchlist,
			$includesMarketOverview,
			$this->datetimeFactory->createNow(),
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

		$run->setResponse($rawResponse, $marketOverviewSummary, $marketOverviewSentiment, $now);

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
			$now,
		);

		$this->entityManager->persist($result);
		$run->addResult($result);
	}

}
