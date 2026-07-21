<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ValidationException;
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
		private readonly StockAiAnalysisV2PromptGenerator|null $v2PromptGenerator = null,
		private readonly StockAiAnalysisV2SchemaFactory|null $v2SchemaFactory = null,
		private readonly StockAiAnalysisV2ResponseValidator|null $v2ResponseValidator = null,
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
			if ($run->isV2()) {
				$this->stockAiAnalysisFacade->processResponse(
					$runId,
					Json::encode($response),
					StockAiAnalysisProcessingSourceEnum::GEMINI,
				);
			} else {
				$this->stockAiAnalysisFacade->processResponse($runId, Json::encode($response));
			}

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
		if ($run->isV2()) {
			return $this->createV2GeminiResponse($run);
		}

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

	/**
	 * @return array<string, mixed>
	 */
	private function createV2GeminiResponse(StockAiAnalysisRun $run): array
	{
		if (
			$this->v2PromptGenerator === null
			|| $this->v2SchemaFactory === null
			|| $this->v2ResponseValidator === null
		) {
			throw new Exception('V2 Gemini services are not configured.');
		}

		$snapshot = $run->getInputSnapshot();
		if ($snapshot === null) {
			throw new Exception('V2 analysis run is missing its input snapshot.');
		}

		$systemInstruction = $this->v2PromptGenerator->generateSystemInstruction($snapshot);
		$portfolioData = $this->getSnapshotList($snapshot, 'portfolio');
		$watchlistData = $this->getSnapshotList($snapshot, 'watchlist');

		if ($portfolioData === [] && $watchlistData === []) {
			$schema = $this->v2SchemaFactory->createFullSchema($snapshot);

			return $this->loadOrCreateV2GeminiResponse(
				$run,
				'manual.json',
				$run->getGeneratedPrompt(),
				$systemInstruction,
				$schema,
				fullSnapshot: $snapshot,
			);
		}

		$portfolioAnalysis = $this->createV2CompanyAnalyses(
			$run,
			$snapshot,
			$portfolioData,
			'portfolioAnalysis',
			'portfolio',
			$systemInstruction,
		);
		$watchlistAnalysis = $this->createV2CompanyAnalyses(
			$run,
			$snapshot,
			$watchlistData,
			'watchlistAnalysis',
			'watchlist',
			$systemInstruction,
		);

		$mergedResponse = [
			'schemaVersion' => 2,
			'runId' => $snapshot['runId'],
			'analysisAsOf' => $snapshot['analysisAsOf'],
		];
		$reduceSchema = $this->v2SchemaFactory->createReduceSchema($snapshot);
		if (is_array($reduceSchema['required'] ?? null) && $reduceSchema['required'] !== []) {
			$reduceResponse = $this->loadOrCreateV2GeminiResponse(
				$run,
				'reduce.json',
				$this->v2PromptGenerator->generateReducePrompt(
					$snapshot,
					$portfolioAnalysis,
					$watchlistAnalysis,
				),
				$systemInstruction,
				$reduceSchema,
			);
			$mergedResponse = [...$mergedResponse, ...$reduceResponse];
		}

		if ($run->includesPortfolio()) {
			$mergedResponse['portfolioAnalysis'] = $portfolioAnalysis;
		}

		if ($run->includesWatchlist()) {
			$mergedResponse['watchlistAnalysis'] = $watchlistAnalysis;
		}

		return $mergedResponse;
	}

	/**
	 * @param array<string, mixed> $snapshot
	 * @param array<int, mixed> $items
	 * @return array<int, array<string, mixed>>
	 */
	private function createV2CompanyAnalyses(
		StockAiAnalysisRun $run,
		array $snapshot,
		array $items,
		string $rootKey,
		string $filePrefix,
		string $systemInstruction,
	): array
	{
		assert($this->v2PromptGenerator !== null);
		assert($this->v2SchemaFactory !== null);
		$schema = $this->v2SchemaFactory->createCompanySchema($rootKey);
		$analyses = [];
		$itemNumber = 1;
		foreach ($items as $item) {
			$item = $this->validateStringKeyArray(TypeValidator::validateArray($item));
			$response = $this->loadOrCreateV2GeminiResponse(
				$run,
				sprintf(
					'%s-%03d-%s.json',
					$filePrefix,
					$itemNumber,
					TypeValidator::validateString($item['stockAssetId'] ?? null),
				),
				$this->v2PromptGenerator->generateCompanyPrompt($snapshot, $rootKey, $item),
				$systemInstruction,
				$schema,
				$rootKey,
				$item,
			);
			$rootValue = TypeValidator::validateArray($response[$rootKey] ?? null);
			$analysis = $rootKey === 'stockAnalysis' ? $rootValue : TypeValidator::validateArray($rootValue[0] ?? null);
			$analyses[] = $this->validateStringKeyArray($analysis);
			$itemNumber++;
		}

		return $analyses;
	}

	/**
	 * @param array<string, mixed> $schema
	 * @param array<string, mixed>|null $expectedItem
	 * @param array<string, mixed>|null $fullSnapshot
	 * @return array<string, mixed>
	 */
	private function loadOrCreateV2GeminiResponse(
		StockAiAnalysisRun $run,
		string $fileName,
		string $prompt,
		string $systemInstruction,
		array $schema,
		string|null $rootKey = null,
		array|null $expectedItem = null,
		array|null $fullSnapshot = null,
	): array
	{
		assert($this->v2SchemaFactory !== null);
		$filePath = $this->getGeminiResponseFilePath($run, $fileName);
		if (is_file($filePath)) {
			try {
				$cached = $this->decodeStrictJsonObject(FileSystem::read($filePath));
				$errors = $this->getV2ValidationErrors($cached, $schema, $rootKey, $expectedItem, $fullSnapshot);
				if ($errors === []) {
					return $cached;
				}
			} catch (Throwable $exception) {
				$this->logger->warning('Cached v2 Gemini response is invalid and will be regenerated.', [
					'fileName' => $fileName,
					'exception' => $exception,
				]);
			}
		}

		$geminiSchema = $this->v2SchemaFactory->toGeminiResponseSchema($schema);
		$lastErrors = [];
		for ($attempt = 1; $attempt <= 2; $attempt++) {
			$requestPrompt = $attempt === 1
				? $prompt
				: $this->createV2RetryPrompt($prompt, $lastErrors);
			$rawResponse = $this->geminiClient->generateContent(
				$requestPrompt,
				$systemInstruction,
				$geminiSchema,
			);

			try {
				$response = $this->decodeStrictJsonObject($rawResponse);
				$lastErrors = $this->getV2ValidationErrors(
					$response,
					$schema,
					$rootKey,
					$expectedItem,
					$fullSnapshot,
				);
				if ($lastErrors === []) {
					$this->writeGeminiResponseFile($filePath, $response);

					return $response;
				}
			} catch (Throwable $exception) {
				$lastErrors = [$exception->getMessage()];
			}

			$this->logger->warning('V2 Gemini response validation failed.', [
				'fileName' => $fileName,
				'attempt' => $attempt,
				'errors' => $lastErrors,
			]);
		}

		throw new Exception(sprintf(
			'Gemini response file "%s" is invalid after retry: %s',
			$fileName,
			implode('; ', $lastErrors),
		));
	}

	/**
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $schema
	 * @param array<string, mixed>|null $expectedItem
	 * @param array<string, mixed>|null $fullSnapshot
	 * @return array<int, string>
	 */
	private function getV2ValidationErrors(
		array $response,
		array $schema,
		string|null $rootKey,
		array|null $expectedItem,
		array|null $fullSnapshot,
	): array
	{
		assert($this->v2ResponseValidator !== null);
		if ($fullSnapshot !== null) {
			try {
				$this->v2ResponseValidator->validate(Json::encode($response), $fullSnapshot);

				return [];
			} catch (StockAiAnalysisV2ValidationException $exception) {
				return $exception->getErrors();
			}
		}

		return $this->v2ResponseValidator->validatePartial($response, $schema, $rootKey, $expectedItem);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeStrictJsonObject(string $rawResponse): array
	{
		$data = Json::decode($rawResponse, forceArrays: true);
		if (!is_array($data)) {
			throw new Exception('Gemini response is not a JSON object.');
		}

		return $this->validateStringKeyArray($data);
	}

	/**
	 * @param array<int, string> $errors
	 */
	private function createV2RetryPrompt(string $prompt, array $errors): string
	{
		return implode("\n\n", [
			$prompt,
			'Your previous response failed validation. Return only corrected JSON with no markdown or explanation.',
			'Validation errors:',
			implode("\n", array_map(static fn (string $error): string => '- ' . $error, $errors)),
		]);
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
		$parts = [
			$this->tempDir,
			'stock-ai-analysis',
			'gemini',
		];
		if ($run->isV2()) {
			$parts[] = 'v2';
		}

		$parts[] = $run->getId()->toString();

		return FileSystem::joinPaths(...$parts);
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

	/**
	 * @param array<string, mixed> $snapshot
	 * @return array<int, mixed>
	 */
	private function getSnapshotList(array $snapshot, string $key): array
	{
		$value = $snapshot[$key] ?? null;
		if (!is_array($value) || !array_is_list($value)) {
			throw new Exception(sprintf('Snapshot key %s must contain a list.', $key));
		}

		return $value;
	}

}
