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
			->with('portfolio prompt')
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
			->with('watchlist prompt')
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
			->with('reduce prompt')
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
		);

		$processor->process($runId);

		self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::COMPLETED, $run->getGeminiProcessingStatus());
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

		$stockAiAnalysisFacade->shouldReceive('getRun')
			->with($runId)
			->once()
			->andReturn($run);
		$stockAiAnalysisFacade->shouldNotReceive('processResponse');
		$geminiClient->shouldReceive('generateContent')
			->with('Generated manual prompt')
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
		);

		self::assertException(
			static fn () => $processor->process($runId),
			Throwable::class,
			'Gemini response does not contain a JSON object.',
		);
		self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::FAILED, $run->getGeminiProcessingStatus());
		self::assertSame('Gemini response does not contain a JSON object.', $run->getGeminiProcessingError());
	}

}
