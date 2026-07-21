<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpQuestionFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiJsonNormalizer;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessingStatusEnum;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2PromptGenerator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2ResponseValidator;
use App\Stock\AiAnalysis\V2\StockAiAnalysisV2SchemaFactory;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

class StockAiAnalysisGeminiProcessorFacadeTest extends UpdatedTestCase
{

	public function testProcessMergesPortfolioWatchlistAndReduceResponse(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated manual prompt',
			true,
			true,
			false,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$runId = $run->getId()->toString();

		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$stockAiAnalysisFollowUpQuestionFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$tempDir = $this->createTempDir();
		$portfolioResponseSchema = ['type' => 'portfolio'];
		$watchlistResponseSchema = ['type' => 'watchlist'];
		$reduceResponseSchema = ['type' => 'reduce'];

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(
				new ImmutableDateTime('2026-05-02 11:00:00'),
				new ImmutableDateTime('2026-05-02 11:05:00'),
			);
		$entityManager->shouldReceive('flush')
			->twice();

		$promptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('system instruction');
		$promptGenerator->shouldReceive('getAutomaticPortfolioData')
			->once()
			->andReturn([
				[
					'stockAssetId' => 'portfolio-stock-id',
					'stockAssetName' => 'Portfolio stock',
					'stockAssetTicker' => 'PORT',
				],
			]);
		$promptGenerator->shouldReceive('generateAutomaticPortfolioStockPrompt')
			->once()
			->andReturn('portfolio prompt');
		$promptGenerator->shouldReceive('generateAutomaticPortfolioStockResponseSchema')
			->with(null)
			->once()
			->andReturn($portfolioResponseSchema);
		$promptGenerator->shouldReceive('getAutomaticWatchlistData')
			->once()
			->andReturn([
				[
					'stockAssetId' => 'watchlist-stock-id',
					'stockAssetName' => 'Watchlist stock',
					'stockAssetTicker' => 'WATCH',
				],
			]);
		$promptGenerator->shouldReceive('generateAutomaticWatchlistStockPrompt')
			->once()
			->andReturn('watchlist prompt');
		$promptGenerator->shouldReceive('generateAutomaticWatchlistStockResponseSchema')
			->with(null)
			->once()
			->andReturn($watchlistResponseSchema);
		$promptGenerator->shouldReceive('generateAutomaticReducePrompt')
			->once()
			->andReturn('reduce prompt');
		$promptGenerator->shouldReceive('generateAutomaticReduceResponseSchema')
			->with(true, false, null)
			->once()
			->andReturn($reduceResponseSchema);

		$geminiClient->shouldReceive('generateContent')
			->with('portfolio prompt', 'system instruction', $portfolioResponseSchema)
			->once()
			->andReturn(
				'{"portfolioAnalysis":.[{"positiveNews":"Portfolio positive news"}]}',
			);
		$geminiClient->shouldReceive('generateContent')
			->with('watchlist prompt', 'system instruction', $watchlistResponseSchema)
			->once()
			->andReturn(Json::encode([
				'watchlistAnalysis' => [
					[
						'positiveNews' => 'Watchlist positive news',
					],
				],
			]));
		$geminiClient->shouldReceive('generateContent')
			->with('reduce prompt', 'system instruction', $reduceResponseSchema)
			->once()
			->andReturn(Json::encode([
				'portfolioEvaluation' => [
					'summary' => 'Portfolio summary',
					'performance7DaysSummary' => 'Performance summary',
				],
			]));

		$stockAiAnalysisFacade->shouldReceive('processResponse')
			->with($runId, Mockery::on(static function (string $rawResponse): bool {
				$data = Json::decode($rawResponse, forceArrays: true);

				return $data['portfolioEvaluation']['summary'] === 'Portfolio summary'
					&& $data['portfolioAnalysis'][0]['stockAssetId'] === 'portfolio-stock-id'
					&& $data['portfolioAnalysis'][0]['stockAssetName'] === 'Portfolio stock'
					&& $data['portfolioAnalysis'][0]['stockAssetTicker'] === 'PORT'
					&& $data['portfolioAnalysis'][0]['positiveNews'] === 'Portfolio positive news'
					&& $data['watchlistAnalysis'][0]['stockAssetId'] === 'watchlist-stock-id'
					&& $data['watchlistAnalysis'][0]['stockAssetName'] === 'Watchlist stock'
					&& $data['watchlistAnalysis'][0]['stockAssetTicker'] === 'WATCH'
					&& $data['watchlistAnalysis'][0]['positiveNews'] === 'Watchlist positive news';
			}))
			->once();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$stockAiAnalysisFollowUpQuestionFacade,
			$promptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
		);

		try {
			$processor->process($runId);

			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::COMPLETED, $run->getGeminiProcessingStatus());
			self::assertFileExists(
				$tempDir . '/stock-ai-analysis/gemini/' . $runId . '/portfolio-001-portfolio-stock-id.json',
			);
			self::assertFileExists(
				$tempDir . '/stock-ai-analysis/gemini/' . $runId . '/watchlist-001-watchlist-stock-id.json',
			);
			self::assertFileExists($tempDir . '/stock-ai-analysis/gemini/' . $runId . '/reduce.json');
		} finally {
			$this->deleteTempDir($tempDir);
		}
	}

	public function testProcessReusesExistingGeminiResponseFiles(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated manual prompt',
			true,
			true,
			false,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$runId = $run->getId()->toString();
		$tempDir = $this->createTempDir();
		$cacheDir = $tempDir . '/stock-ai-analysis/gemini/' . $runId;
		FileSystem::createDir($cacheDir);
		FileSystem::write($cacheDir . '/portfolio-001-portfolio-stock-id.json', Json::encode([
			'portfolioAnalysis' => [
				[
					'stockAssetId' => 'portfolio-stock-id',
					'stockAssetName' => 'Portfolio stock',
					'stockAssetTicker' => 'PORT',
				],
			],
		]));
		FileSystem::write($cacheDir . '/watchlist-001-watchlist-stock-id.json', Json::encode([
			'watchlistAnalysis' => [
				[
					'stockAssetId' => 'watchlist-stock-id',
					'stockAssetName' => 'Watchlist stock',
					'stockAssetTicker' => 'WATCH',
				],
			],
		]));
		FileSystem::write($cacheDir . '/reduce.json', Json::encode([
			'portfolioEvaluation' => [
				'summary' => 'Cached portfolio summary',
				'performance7DaysSummary' => 'Cached performance summary',
			],
		]));

		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$stockAiAnalysisFollowUpQuestionFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(
				new ImmutableDateTime('2026-05-02 11:00:00'),
				new ImmutableDateTime('2026-05-02 11:05:00'),
			);
		$entityManager->shouldReceive('flush')
			->twice();
		$promptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('system instruction');
		$promptGenerator->shouldReceive('getAutomaticPortfolioData')
			->once()
			->andReturn([
				[
					'stockAssetId' => 'portfolio-stock-id',
					'stockAssetName' => 'Portfolio stock',
					'stockAssetTicker' => 'PORT',
				],
			]);
		$promptGenerator->shouldReceive('generateAutomaticPortfolioStockPrompt')
			->once()
			->andReturn('portfolio prompt');
		$promptGenerator->shouldReceive('generateAutomaticPortfolioStockResponseSchema')
			->with(null)
			->once()
			->andReturn(['type' => 'portfolio']);
		$promptGenerator->shouldReceive('getAutomaticWatchlistData')
			->once()
			->andReturn([
				[
					'stockAssetId' => 'watchlist-stock-id',
					'stockAssetName' => 'Watchlist stock',
					'stockAssetTicker' => 'WATCH',
				],
			]);
		$promptGenerator->shouldReceive('generateAutomaticWatchlistStockPrompt')
			->once()
			->andReturn('watchlist prompt');
		$promptGenerator->shouldReceive('generateAutomaticWatchlistStockResponseSchema')
			->with(null)
			->once()
			->andReturn(['type' => 'watchlist']);
		$promptGenerator->shouldReceive('generateAutomaticReducePrompt')
			->once()
			->andReturn('reduce prompt');
		$promptGenerator->shouldReceive('generateAutomaticReduceResponseSchema')
			->with(true, false, null)
			->once()
			->andReturn(['type' => 'reduce']);
		$geminiClient->shouldNotReceive('generateContent');
		$stockAiAnalysisFacade->shouldReceive('processResponse')
			->with($runId, Mockery::on(static function (string $rawResponse): bool {
				$data = Json::decode($rawResponse, forceArrays: true);

				return $data['portfolioEvaluation']['summary'] === 'Cached portfolio summary'
					&& $data['portfolioAnalysis'][0]['stockAssetTicker'] === 'PORT'
					&& $data['watchlistAnalysis'][0]['stockAssetTicker'] === 'WATCH';
			}))
			->once();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$stockAiAnalysisFollowUpQuestionFacade,
			$promptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
		);

		try {
			$processor->process($runId);

			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::COMPLETED, $run->getGeminiProcessingStatus());
		} finally {
			$this->deleteTempDir($tempDir);
		}
	}

	public function testProcessRetriesOnceWhenGeminiResponseIsInvalid(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated manual prompt',
			false,
			false,
			true,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$runId = $run->getId()->toString();
		$manualResponseSchema = ['type' => 'manual'];
		$tempDir = $this->createTempDir();

		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$stockAiAnalysisFollowUpQuestionFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$promptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('system instruction');
		$promptGenerator->shouldReceive('generateResponseSchema')
			->with(false, false, true, null, null, null)
			->once()
			->andReturn($manualResponseSchema);
		$geminiClient->shouldReceive('generateContent')
			->with('Generated manual prompt', 'system instruction', $manualResponseSchema)
			->once()
			->andReturn('{"marketOverview":. invalid}');
		$geminiClient->shouldReceive('generateContent')
			->with(
				Mockery::on(static fn (string $prompt): bool => str_starts_with($prompt, 'Generated manual prompt')
					&& str_contains($prompt, 'Your previous response was invalid JSON')),
				'system instruction',
				$manualResponseSchema,
			)
			->once()
			->andReturn(Json::encode([
				'marketOverview' => [
					'summary' => 'Retry summary',
				],
			]));
		$stockAiAnalysisFacade->shouldReceive('processResponse')
			->with($runId, Mockery::on(static function (string $rawResponse): bool {
				$data = Json::decode($rawResponse, forceArrays: true);

				return $data['marketOverview']['summary'] === 'Retry summary';
			}))
			->once();
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(
				new ImmutableDateTime('2026-05-02 11:00:00'),
				new ImmutableDateTime('2026-05-02 11:05:00'),
			);
		$entityManager->shouldReceive('flush')
			->twice();
		$logger->shouldReceive('warning')
			->once();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$stockAiAnalysisFollowUpQuestionFacade,
			$promptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
		);

		try {
			$processor->process($runId);

			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::COMPLETED, $run->getGeminiProcessingStatus());
			self::assertSame([
				'marketOverview' => [
					'summary' => 'Retry summary',
				],
			], Json::decode(
				FileSystem::read($tempDir . '/stock-ai-analysis/gemini/' . $runId . '/manual.json'),
				forceArrays: true,
			));
		} finally {
			$this->deleteTempDir($tempDir);
		}
	}

	public function testProcessMarksRunAsFailedWhenGeminiResponseIsInvalid(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated manual prompt',
			false,
			false,
			true,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$runId = $run->getId()->toString();

		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$stockAiAnalysisFollowUpQuestionFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$manualResponseSchema = ['type' => 'manual'];
		$tempDir = $this->createTempDir();

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$stockAiAnalysisFacade->shouldNotReceive('processResponse');
		$promptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('system instruction');
		$promptGenerator->shouldReceive('generateResponseSchema')
			->with(false, false, true, null, null, null)
			->once()
			->andReturn($manualResponseSchema);
		$geminiClient->shouldReceive('generateContent')
			->with('Generated manual prompt', 'system instruction', $manualResponseSchema)
			->once()
			->andReturn('not json');
		$geminiClient->shouldReceive('generateContent')
			->with(
				Mockery::on(static fn (string $prompt): bool => str_starts_with($prompt, 'Generated manual prompt')
					&& str_contains($prompt, 'Your previous response was invalid JSON')),
				'system instruction',
				$manualResponseSchema,
			)
			->once()
			->andReturn('still not json');
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(
				new ImmutableDateTime('2026-05-02 11:00:00'),
				new ImmutableDateTime('2026-05-02 11:05:00'),
			);
		$entityManager->shouldReceive('flush')
			->twice();
		$logger->shouldReceive('error')
			->once();
		$logger->shouldReceive('warning')
			->twice();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$stockAiAnalysisFollowUpQuestionFacade,
			$promptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
		);

		try {
			self::assertException(
				static fn () => $processor->process($runId),
				Throwable::class,
				'Gemini response file "manual.json" could not be parsed after retry: Gemini response does not contain a JSON object.',
			);
			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::FAILED, $run->getGeminiProcessingStatus());
			self::assertSame(
				'Gemini response file "manual.json" could not be parsed after retry: Gemini response does not contain a JSON object.',
				$run->getGeminiProcessingError(),
			);
			self::assertFileDoesNotExist($tempDir . '/stock-ai-analysis/gemini/' . $runId . '/manual.json');
		} finally {
			$this->deleteTempDir($tempDir);
		}
	}

	public function testProcessV2UsesStrictSchemaAndProcessingSource(): void
	{
		$runId = Uuid::uuid4();
		$snapshot = [
			'schemaVersion' => 2,
			'runId' => $runId->toString(),
			'analysisAsOf' => '2026-07-21T10:00:00+02:00',
			'scope' => [
				'includesPortfolio' => false,
				'includesWatchlist' => false,
				'includesMarketOverview' => true,
				'includesStockAnalysis' => false,
				'portfolioPromptType' => null,
			],
			'portfolio' => [],
			'watchlist' => [],
			'portfolioContext' => [],
			'singleStock' => null,
		];
		$run = new StockAiAnalysisRun(
			'Generated v2 prompt',
			false,
			false,
			true,
			null,
			new ImmutableDateTime('2026-07-21 10:00:00'),
			analysisSchemaVersion: 2,
			inputSnapshot: $snapshot,
			id: $runId,
		);
		$response = [
			'schemaVersion' => 2,
			'runId' => $runId->toString(),
			'analysisAsOf' => $snapshot['analysisAsOf'],
			'marketOverview' => [
				'summary' => 'Trh je stabilní.',
				'sentiment' => 'neutral',
				'keyDrivers' => [],
				'upcomingEvents' => [],
				'geopoliticalRisks' => [],
			],
		];
		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$followUpFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$legacyPromptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$tempDir = $this->createTempDir();
		$schemaFactory = new StockAiAnalysisV2SchemaFactory();
		$v2PromptGenerator = new StockAiAnalysisV2PromptGenerator($schemaFactory);

		$stockAiAnalysisFacade->shouldReceive('getRun')->with($runId->toString())->once()->andReturn($run);
		$geminiClient->shouldReceive('generateContent')
			->with('Generated v2 prompt', Mockery::type('string'), Mockery::type('array'))
			->once()
			->andReturn(Json::encode($response));
		$stockAiAnalysisFacade->shouldReceive('processResponse')
			->with($runId->toString(), Json::encode($response), StockAiAnalysisProcessingSourceEnum::GEMINI)
			->once();
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(
				new ImmutableDateTime('2026-07-21 10:01:00'),
				new ImmutableDateTime('2026-07-21 10:02:00'),
			);
		$entityManager->shouldReceive('flush')->twice();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$followUpFacade,
			$legacyPromptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
			$v2PromptGenerator,
			$schemaFactory,
			new StockAiAnalysisV2ResponseValidator($schemaFactory),
		);

		try {
			$processor->process($runId->toString());

			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::COMPLETED, $run->getGeminiProcessingStatus());
			self::assertFileExists(
				$tempDir . '/stock-ai-analysis/gemini/v2/' . $runId->toString() . '/manual.json',
			);
		} finally {
			$this->deleteTempDir($tempDir);
		}
	}

	public function testProcessFollowUpDelegatesToFollowUpFacade(): void
	{
		$stockAiAnalysisFacade = Mockery::mock(StockAiAnalysisFacade::class);
		$stockAiAnalysisFollowUpQuestionFacade = Mockery::mock(StockAiAnalysisFollowUpQuestionFacade::class);
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);

		$stockAiAnalysisFollowUpQuestionFacade->shouldReceive('processGeminiQuestion')
			->with('question-1')
			->once();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$stockAiAnalysisFollowUpQuestionFacade,
			$promptGenerator,
			$geminiClient,
			new StockAiAnalysisGeminiJsonNormalizer(),
			$datetimeFactory,
			$entityManager,
			$logger,
			sys_get_temp_dir(),
		);

		$processor->processFollowUp('question-1');

		self::assertTrue(true);
	}

	private function createTempDir(): string
	{
		$tempDir = sys_get_temp_dir() . '/stock_ai_analysis_gemini_processor_' . uniqid();
		FileSystem::createDir($tempDir);

		return $tempDir;
	}

	private function deleteTempDir(string $tempDir): void
	{
		FileSystem::delete($tempDir);
	}

}
