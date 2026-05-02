<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Throwable;

class StockAiAnalysisGeminiProcessorFacade
{

	public function __construct(
		private readonly StockAiAnalysisFacade $stockAiAnalysisFacade,
		private readonly StockAiAnalysisPromptGenerator $promptGenerator,
		private readonly GeminiClient $geminiClient,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggerInterface $logger,
	)
	{
	}

	public function process(string $runId): void
	{
		$run = $this->stockAiAnalysisFacade->getRun($runId);
		if ($run->getProcessedAt() !== null) {
			return;
		}

		$run->markGeminiProcessing($this->datetimeFactory->createNow());
		$this->entityManager->flush();

		try {
			$response = $this->createGeminiResponse($run);
			$this->stockAiAnalysisFacade->processResponse($runId, Json::encode($response));
			$run->markGeminiCompleted($this->datetimeFactory->createNow());
			$this->entityManager->flush();
		} catch (Throwable $exception) {
			$run->markGeminiFailed($this->datetimeFactory->createNow(), $exception->getMessage());
			$this->entityManager->flush();
			$this->logger->error('Gemini stock AI analysis processing failed', [
				'runId' => $runId,
				'exception' => $exception,
			]);

			throw $exception;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createGeminiResponse(StockAiAnalysisRun $run): array
	{
		if (!$run->includesPortfolio() && !$run->includesWatchlist()) {
			return $this->decodeJsonObject($this->geminiClient->generateContent($run->getGeneratedPrompt()));
		}

		$portfolioAnalysis = [];
		if ($run->includesPortfolio()) {
			foreach ($this->promptGenerator->getAutomaticPortfolioData() as $portfolioItem) {
				$portfolioItem = $this->validateStringKeyArray(TypeValidator::validateArray($portfolioItem));
				$response = $this->decodeJsonObject($this->geminiClient->generateContent(
					$this->promptGenerator->generateAutomaticPortfolioStockPrompt(
						$portfolioItem,
						$run->getPortfolioPromptType(),
					),
				));
				$portfolioAnalysis[] = $this->extractAnalysisItem($response, 'portfolioAnalysis');
			}
		}

		$watchlistAnalysis = [];
		if ($run->includesWatchlist()) {
			foreach ($this->promptGenerator->getAutomaticWatchlistData() as $watchlistItem) {
				$watchlistItem = $this->validateStringKeyArray(TypeValidator::validateArray($watchlistItem));
				$response = $this->decodeJsonObject($this->geminiClient->generateContent(
					$this->promptGenerator->generateAutomaticWatchlistStockPrompt(
						$watchlistItem,
						$run->getPortfolioPromptType(),
					),
				));
				$watchlistAnalysis[] = $this->extractAnalysisItem($response, 'watchlistAnalysis');
			}
		}

		$mergedResponse = [];
		if ($this->needsReduceStep($run)) {
			$mergedResponse = $this->decodeJsonObject($this->geminiClient->generateContent(
				$this->promptGenerator->generateAutomaticReducePrompt(
					$run->includesPortfolio(),
					$run->includesWatchlist(),
					$run->includesMarketOverview(),
					$run->getPortfolioPromptType(),
					$portfolioAnalysis,
					$watchlistAnalysis,
				),
			));
		}

		if ($run->includesPortfolio()) {
			$mergedResponse['portfolioAnalysis'] = $portfolioAnalysis;
		}

		if ($run->includesWatchlist()) {
			$mergedResponse['watchlistAnalysis'] = $watchlistAnalysis;
		}

		return $mergedResponse;
	}

	private function needsReduceStep(StockAiAnalysisRun $run): bool
	{
		return $run->includesMarketOverview() || $run->includesPortfolio();
	}

	/**
	 * @param array<string, mixed> $response
	 * @return array<string, mixed>
	 */
	private function extractAnalysisItem(array $response, string $key): array
	{
		$analysis = TypeValidator::validateArray($response[$key] ?? null);
		$item = $analysis[0] ?? null;

		return $this->validateStringKeyArray(TypeValidator::validateArray($item));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonObject(string $response): array
	{
		$response = trim($response);
		if (str_starts_with($response, '```')) {
			$response = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $response) ?? $response;
		}

		$start = strpos($response, '{');
		$end = strrpos($response, '}');
		if ($start === false || $end === false || $end < $start) {
			throw new Exception('Gemini response does not contain a JSON object.');
		}

		$data = Json::decode(substr($response, $start, $end - $start + 1), forceArrays: true);
		if (!is_array($data)) {
			throw new Exception('Gemini response is not a JSON object.');
		}

		return $this->validateStringKeyArray($data);
	}

	/**
	 * @param array<mixed> $data
	 * @return array<string, mixed>
	 */
	private function validateStringKeyArray(array $data): array
	{
		$result = [];
		foreach ($data as $key => $value) {
			if (!is_string($key)) {
				throw new Exception('Gemini response contains invalid array keys.');
			}

			$result[$key] = $value;
		}

		return $result;
	}

}
