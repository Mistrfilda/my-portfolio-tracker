<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Utils\TypeValidator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JsonException;
use Mistrfilda\Datetime\DatetimeFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Throwable;

class StockAiAnalysisGeminiProcessorFacade
{

	private const RETRY_INSTRUCTION = 'Your previous response was invalid JSON. Return only syntactically valid JSON '
		. 'matching the provided schema. Do not include markdown fences, explanations, or any text outside the JSON object.';

	public function __construct(
		private readonly StockAiAnalysisFacade $stockAiAnalysisFacade,
		private readonly StockAiAnalysisFollowUpQuestionFacade $stockAiAnalysisFollowUpQuestionFacade,
		private readonly StockAiAnalysisPromptGenerator $promptGenerator,
		private readonly GeminiClient $geminiClient,
		private readonly StockAiAnalysisGeminiJsonNormalizer $geminiJsonNormalizer,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly EntityManagerInterface $entityManager,
		private readonly LoggerInterface $logger,
		private readonly string $tempDir,
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

	public function processFollowUp(string $questionId): void
	{
		$this->stockAiAnalysisFollowUpQuestionFacade->processGeminiQuestion($questionId);
	}

	public function getCachedGeminiResponseFileCount(StockAiAnalysisRun $run): int
	{
		$directory = $this->getGeminiResponseDirectory($run);
		if (!is_dir($directory)) {
			return 0;
		}

		$fileCount = 0;
		$filenames = scandir($directory);
		foreach ($filenames === false ? [] : $filenames as $fileName) {
			$filePath = FileSystem::joinPaths($directory, $fileName);
			if (str_ends_with($fileName, '.json') && is_file($filePath)) {
				$fileCount++;
			}
		}

		return $fileCount;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createGeminiResponse(StockAiAnalysisRun $run): array
	{
		$systemInstruction = $this->promptGenerator->generateSystemInstruction();

		if (!$run->includesPortfolio() && !$run->includesWatchlist()) {
			return $this->loadOrCreateGeminiResponse(
				$run,
				'manual.json',
				$run->getGeneratedPrompt(),
				$systemInstruction,
				$this->promptGenerator->generateResponseSchema(
					$run->includesPortfolio(),
					$run->includesWatchlist(),
					$run->includesMarketOverview(),
					$run->getPortfolioPromptType(),
					$run->getStockTicker(),
					$run->getStockName(),
				),
			);
		}

		$portfolioAnalysis = [];
		if ($run->includesPortfolio()) {
			$portfolioItemNumber = 1;
			foreach ($this->promptGenerator->getAutomaticPortfolioData() as $portfolioItem) {
				$portfolioItem = $this->validateStringKeyArray(TypeValidator::validateArray($portfolioItem));
				$response = $this->loadOrCreateGeminiResponse(
					$run,
					sprintf(
						'portfolio-%03d-%s.json',
						$portfolioItemNumber,
						TypeValidator::validateString($portfolioItem['stockAssetId'] ?? null),
					),
					$this->promptGenerator->generateAutomaticPortfolioStockPrompt(
						$portfolioItem,
						$run->getPortfolioPromptType(),
					),
					$systemInstruction,
					$this->promptGenerator->generateAutomaticPortfolioStockResponseSchema(
						$run->getPortfolioPromptType(),
					),
				);
				$portfolioAnalysis[] = $this->extractAnalysisItem($response, 'portfolioAnalysis', $portfolioItem);
				$portfolioItemNumber++;
			}
		}

		$watchlistAnalysis = [];
		if ($run->includesWatchlist()) {
			$watchlistItemNumber = 1;
			foreach ($this->promptGenerator->getAutomaticWatchlistData() as $watchlistItem) {
				$watchlistItem = $this->validateStringKeyArray(TypeValidator::validateArray($watchlistItem));
				$response = $this->loadOrCreateGeminiResponse(
					$run,
					sprintf(
						'watchlist-%03d-%s.json',
						$watchlistItemNumber,
						TypeValidator::validateString($watchlistItem['stockAssetId'] ?? null),
					),
					$this->promptGenerator->generateAutomaticWatchlistStockPrompt(
						$watchlistItem,
						$run->getPortfolioPromptType(),
					),
					$systemInstruction,
					$this->promptGenerator->generateAutomaticWatchlistStockResponseSchema(
						$run->getPortfolioPromptType(),
					),
				);
				$watchlistAnalysis[] = $this->extractAnalysisItem($response, 'watchlistAnalysis', $watchlistItem);
				$watchlistItemNumber++;
			}
		}

		$mergedResponse = [];
		if ($this->needsReduceStep($run)) {
			$mergedResponse = $this->loadOrCreateGeminiResponse(
				$run,
				'reduce.json',
				$this->promptGenerator->generateAutomaticReducePrompt(
					$run->includesPortfolio(),
					$run->includesWatchlist(),
					$run->includesMarketOverview(),
					$run->getPortfolioPromptType(),
					$portfolioAnalysis,
					$watchlistAnalysis,
				),
				$systemInstruction,
				$this->promptGenerator->generateAutomaticReduceResponseSchema(
					$run->includesPortfolio(),
					$run->includesMarketOverview(),
					$run->getPortfolioPromptType(),
				),
			);
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
	 * @param array<string, mixed> $responseSchema
	 * @return array<string, mixed>
	 */
	private function loadOrCreateGeminiResponse(
		StockAiAnalysisRun $run,
		string $fileName,
		string $prompt,
		string $systemInstruction,
		array $responseSchema,
	): array
	{
		$filePath = $this->getGeminiResponseFilePath($run, $fileName);
		if (is_file($filePath)) {
			$data = Json::decode(FileSystem::read($filePath), forceArrays: true);

			return $this->validateStringKeyArray(TypeValidator::validateArray($data));
		}

		$rawResponse = $this->geminiClient->generateContent($prompt, $systemInstruction, $responseSchema);
		try {
			$response = $this->decodeJsonObject($rawResponse);
		} catch (Throwable $firstException) {
			$this->logGeminiJsonParseFailure($fileName, $firstException, $rawResponse);

			$retryRawResponse = $this->geminiClient->generateContent(
				$this->createRetryPrompt($prompt),
				$systemInstruction,
				$responseSchema,
			);

			try {
				$response = $this->decodeJsonObject($retryRawResponse);
			} catch (Throwable $retryException) {
				$this->logGeminiJsonParseFailure($fileName, $retryException, $retryRawResponse);

				throw new Exception(
					sprintf(
						'Gemini response file "%s" could not be parsed after retry: %s',
						$fileName,
						$retryException->getMessage(),
					),
					0,
					$retryException,
				);
			}
		}

		$this->writeGeminiResponseFile($filePath, $response);

		return $response;
	}

	private function createRetryPrompt(string $prompt): string
	{
		return $prompt . "\n\n" . self::RETRY_INSTRUCTION;
	}

	private function logGeminiJsonParseFailure(string $fileName, Throwable $exception, string $response): void
	{
		$this->logger->warning('Gemini response is not a valid JSON object.', [
			'fileName' => $fileName,
			'jsonError' => $exception->getMessage(),
			'responseSnippet' => $this->createResponseSnippet($response),
		]);
	}

	private function createResponseSnippet(string $response): string
	{
		$response = preg_replace('/\s+/', ' ', trim($response)) ?? $response;

		return mb_substr($response, 0, 500);
	}

	private function getGeminiResponseDirectory(StockAiAnalysisRun $run): string
	{
		return FileSystem::joinPaths(
			$this->tempDir,
			'stock-ai-analysis',
			'gemini',
			$run->getId()->toString(),
		);
	}

	private function getGeminiResponseFilePath(StockAiAnalysisRun $run, string $fileName): string
	{
		return FileSystem::joinPaths($this->getGeminiResponseDirectory($run), $fileName);
	}

	/**
	 * @param array<string, mixed> $response
	 */
	private function writeGeminiResponseFile(string $filePath, array $response): void
	{
		FileSystem::createDir(dirname($filePath));

		$temporaryFilePath = $filePath . '.tmp.' . bin2hex(random_bytes(8));
		FileSystem::write($temporaryFilePath, Json::encode($response, pretty: true));
		FileSystem::rename($temporaryFilePath, $filePath, true);
	}

	/**
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $stockData
	 * @return array<string, mixed>
	 */
	private function extractAnalysisItem(array $response, string $key, array $stockData): array
	{
		$analysis = TypeValidator::validateArray($response[$key] ?? null);
		$item = $analysis[0] ?? null;

		return $this->withStockAssetIdentity(
			$this->validateStringKeyArray(TypeValidator::validateArray($item)),
			$stockData,
		);
	}

	/**
	 * @param array<string, mixed> $analysisItem
	 * @param array<string, mixed> $stockData
	 * @return array<string, mixed>
	 */
	private function withStockAssetIdentity(array $analysisItem, array $stockData): array
	{
		$analysisItem['stockAssetId'] = TypeValidator::validateString($stockData['stockAssetId'] ?? null);
		$analysisItem['stockAssetName'] = TypeValidator::validateString($stockData['stockAssetName'] ?? null);
		$analysisItem['stockAssetTicker'] = TypeValidator::validateString($stockData['stockAssetTicker'] ?? null);

		return $analysisItem;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJsonObject(string $response): array
	{
		$response = $this->geminiJsonNormalizer->normalize($response);

		$start = strpos($response, '{');
		$end = strrpos($response, '}');
		if ($start === false || $end === false || $end < $start) {
			throw new Exception('Gemini response does not contain a JSON object.');
		}

		try {
			$data = Json::decode(substr($response, $start, $end - $start + 1), forceArrays: true);
		} catch (JsonException $e) {
			throw $e;
		}

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
