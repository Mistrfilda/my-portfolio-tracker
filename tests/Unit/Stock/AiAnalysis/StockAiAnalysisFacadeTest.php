<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisMarketSentimentEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Utils\Json;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Throwable;

class StockAiAnalysisFacadeTest extends TestCase
{

	private StockAiAnalysisFacade $facade;

	private StockAiAnalysisPromptGenerator $promptGenerator;

	private StockAiAnalysisRunRepository $stockAiAnalysisRunRepository;

	private StockAssetRepository $stockAssetRepository;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	public function setUp(): void
	{
		$this->promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisPromptGenerator::class);
		$this->stockAiAnalysisRunRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisRunRepository::class,
		);
		$this->stockAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$this->facade = new StockAiAnalysisFacade(
			$this->promptGenerator,
			$this->stockAiAnalysisRunRepository,
			$this->stockAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
		);
	}

	public function testCreateRun(): void
	{
		$now = new ImmutableDateTime();

		$this->promptGenerator->shouldReceive('generate')
			->with(true, true, false, null, null)
			->once()
			->andReturn('Generated prompt text');

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$run = $this->facade->createRun(true, true, false);

		self::assertSame('Generated prompt text', $run->getGeneratedPrompt());
		self::assertTrue($run->includesPortfolio());
		self::assertTrue($run->includesWatchlist());
		self::assertFalse($run->includesMarketOverview());
	}

	public function testProcessResponseWithMarketOverview(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, true, true, $now);

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'marketOverview' => [
				'summary' => 'Trhy jsou stabilní',
				'sentiment' => 'bullish',
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertSame($response, $run->getRawResponse());
		self::assertSame('Trhy jsou stabilní', $run->getMarketOverviewSummary());
		self::assertSame(StockAiAnalysisMarketSentimentEnum::BULLISH, $run->getMarketOverviewSentiment());
		self::assertSame($now, $run->getProcessedAt());
	}

	public function testProcessResponseWithPortfolioAnalysis(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, $now);
		$stockAssetId = Uuid::uuid4();

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$this->stockAssetRepository->shouldReceive('getById')
			->once()
			->andReturn($stockAsset);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'portfolioAnalysis' => [
				[
					'stockAssetId' => $stockAssetId->toString(),
					'positiveNews' => 'Rostoucí tržby',
					'negativeNews' => 'Vysoké náklady',
					'interestingNews' => 'Nový produkt',
					'aiOpinion' => 'Stabilní pozice',
					'actionSuggestion' => 'hold',
					'reasoning' => 'Dobrá fundamentální analýza',
					'news' => 'Novinky o firmě',
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertSame(StockAiAnalysisResultTypeEnum::PORTFOLIO, $result->getType());
		self::assertSame('Rostoucí tržby', $result->getPositiveNews());
		self::assertSame('Vysoké náklady', $result->getNegativeNews());
		self::assertSame('Nový produkt', $result->getInterestingNews());
		self::assertSame('Stabilní pozice', $result->getAiOpinion());
		self::assertSame(StockAiAnalysisActionSuggestionEnum::HOLD, $result->getActionSuggestion());
		self::assertSame('Dobrá fundamentální analýza', $result->getReasoning());
		self::assertSame('Novinky o firmě', $result->getNews());
	}

	public function testProcessResponseWithWatchlistAnalysis(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', false, true, false, $now);
		$stockAssetId = Uuid::uuid4();

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$this->stockAssetRepository->shouldReceive('getById')
			->once()
			->andReturn($stockAsset);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'watchlistAnalysis' => [
				[
					'stockAssetId' => $stockAssetId->toString(),
					'positiveNews' => 'Dobrý výhled',
					'negativeNews' => null,
					'interestingNews' => null,
					'aiOpinion' => 'Zajímavá akcie',
					'buyRecommendation' => 'consider_buying',
					'reasoning' => 'Nízké ocenění',
					'news' => null,
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertSame(StockAiAnalysisResultTypeEnum::WATCHLIST, $result->getType());
		self::assertSame('Dobrý výhled', $result->getPositiveNews());
		self::assertNull($result->getNegativeNews());
		self::assertSame(StockAiAnalysisActionSuggestionEnum::CONSIDER_BUYING, $result->getActionSuggestion());
	}

	public function testProcessResponseInvalidJson(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, true, true, $now);

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->expectException(Throwable::class);
		$this->expectExceptionMessage('Invalid JSON response');
		$this->facade->processResponse($run->getId()->toString(), 'invalid json');
	}

	public function testProcessResponseWithMultiplePortfolioStocks(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, $now);

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$stockAsset1 = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$stockAsset2 = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);

		$this->stockAssetRepository->shouldReceive('getById')
			->twice()
			->andReturn($stockAsset1, $stockAsset2);

		$this->entityManager->shouldReceive('persist')
			->twice();

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'portfolioAnalysis' => [
				[
					'stockAssetId' => Uuid::uuid4()->toString(),
					'positiveNews' => 'Pozitivní 1',
					'actionSuggestion' => 'hold',
				],
				[
					'stockAssetId' => Uuid::uuid4()->toString(),
					'positiveNews' => 'Pozitivní 2',
					'actionSuggestion' => 'add_more',
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(2, $run->getResults());
	}

	public function testProcessResponseWithSingleStockAnalysis(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', false, false, false, $now, 'AAPL', 'Apple Inc.');

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'stockAnalysis' => [
				'businessSummary' => 'Summary',
				'recommendation' => 'consider_buying',
				'financialHealth' => 'Strong',
				'valuationAssessment' => 'Undervalued',
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertSame(StockAiAnalysisResultTypeEnum::SINGLE_STOCK, $result->getType());
		self::assertSame('AAPL', $result->getStockTicker());
		self::assertSame('Apple Inc.', $result->getStockName());
		self::assertSame('Summary', $result->getBusinessSummary());
		self::assertSame(StockAiAnalysisActionSuggestionEnum::CONSIDER_BUYING, $result->getActionSuggestion());
		self::assertSame('Strong', $result->getFinancialHealth());
		self::assertSame('Undervalued', $result->getValuationAssessment());
	}

}
