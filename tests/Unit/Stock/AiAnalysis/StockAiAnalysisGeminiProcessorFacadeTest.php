<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Ai\Gemini\GeminiClient;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessingStatusEnum;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessorFacade;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
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
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$tempDir = $this->createTempDir();

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
		$promptGenerator->shouldReceive('generateAutomaticReducePrompt')
			->once()
			->andReturn('reduce prompt');

		$geminiClient->shouldReceive('generateContent')
			->with('portfolio prompt', 'system instruction')
			->once()
			->andReturn(Json::encode([
				'portfolioAnalysis' => [
					[
						'stockAssetId' => 'portfolio-stock-id',
						'stockAssetName' => 'Portfolio stock',
						'stockAssetTicker' => 'PORT',
					],
				],
			]));
		$geminiClient->shouldReceive('generateContent')
			->with('watchlist prompt', 'system instruction')
			->once()
			->andReturn(Json::encode([
				'watchlistAnalysis' => [
					[
						'stockAssetId' => 'watchlist-stock-id',
						'stockAssetName' => 'Watchlist stock',
						'stockAssetTicker' => 'WATCH',
					],
				],
			]));
		$geminiClient->shouldReceive('generateContent')
			->with('reduce prompt', 'system instruction')
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
					&& $data['portfolioAnalysis'][0]['stockAssetTicker'] === 'PORT'
					&& $data['watchlistAnalysis'][0]['stockAssetTicker'] === 'WATCH';
			}))
			->once();

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$promptGenerator,
			$geminiClient,
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
		$promptGenerator->shouldReceive('generateAutomaticReducePrompt')
			->once()
			->andReturn('reduce prompt');
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
			$promptGenerator,
			$geminiClient,
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
		$promptGenerator = Mockery::mock(StockAiAnalysisPromptGenerator::class);
		$geminiClient = Mockery::mock(GeminiClient::class);
		$datetimeFactory = Mockery::mock(DatetimeFactory::class);
		$entityManager = Mockery::mock(EntityManagerInterface::class);
		$logger = Mockery::mock(LoggerInterface::class);
		$tempDir = $this->createTempDir();

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$stockAiAnalysisFacade->shouldNotReceive('processResponse');
		$promptGenerator->shouldReceive('generateSystemInstruction')
			->once()
			->andReturn('system instruction');
		$geminiClient->shouldReceive('generateContent')
			->with('Generated manual prompt', 'system instruction')
			->once()
			->andReturn('not json');
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

		$processor = new StockAiAnalysisGeminiProcessorFacade(
			$stockAiAnalysisFacade,
			$promptGenerator,
			$geminiClient,
			$datetimeFactory,
			$entityManager,
			$logger,
			$tempDir,
		);

		try {
			self::assertException(
				static fn () => $processor->process($runId),
				Throwable::class,
				'Gemini response does not contain a JSON object.',
			);
			self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::FAILED, $run->getGeminiProcessingStatus());
			self::assertSame('Gemini response does not contain a JSON object.', $run->getGeminiProcessingError());
		} finally {
			$this->deleteTempDir($tempDir);
		}
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
