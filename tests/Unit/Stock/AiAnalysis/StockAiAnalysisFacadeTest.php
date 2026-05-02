<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Currency\CurrencyEnum;
use App\JobRequest\JobRequestFacade;
use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisConfidenceLevelEnum;
use App\Stock\AiAnalysis\StockAiAnalysisDailyBriefActionNeededEnum;
use App\Stock\AiAnalysis\StockAiAnalysisFacade;
use App\Stock\AiAnalysis\StockAiAnalysisGeminiProcessingStatusEnum;
use App\Stock\AiAnalysis\StockAiAnalysisMarketSentimentEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
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

	private JobRequestFacade $jobRequestFacade;

	public function setUp(): void
	{
		$this->promptGenerator = UpdatedTestCase::createMockWithIgnoreMethods(StockAiAnalysisPromptGenerator::class);
		$this->stockAiAnalysisRunRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAiAnalysisRunRepository::class,
		);
		$this->stockAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$this->entityManager = UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);
		$this->jobRequestFacade = UpdatedTestCase::createMockWithIgnoreMethods(JobRequestFacade::class);

		$this->facade = new StockAiAnalysisFacade(
			$this->promptGenerator,
			$this->stockAiAnalysisRunRepository,
			$this->stockAssetRepository,
			$this->entityManager,
			$this->datetimeFactory,
			$this->jobRequestFacade,
		);
	}

	public function testCreateRun(): void
	{
		$now = new ImmutableDateTime();

		$this->promptGenerator->shouldReceive('generate')
			->with(true, true, false, null, null, null)
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
		self::assertNull($run->getPortfolioPromptType());
	}

	public function testEnqueueGeminiProcessing(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated prompt text',
			true,
			true,
			false,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$now = new ImmutableDateTime('2026-05-02 11:00:00');

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);
		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);
		$this->entityManager->shouldReceive('flush')
			->once();
		$this->jobRequestFacade->shouldReceive('addStockAiAnalysisGeminiProcessToQueue')
			->with($run->getId()->toString())
			->once();

		$this->facade->enqueueGeminiProcessing($run->getId()->toString());

		self::assertSame(StockAiAnalysisGeminiProcessingStatusEnum::QUEUED, $run->getGeminiProcessingStatus());
		self::assertSame($now, $run->getGeminiProcessingQueuedAt());
		self::assertNull($run->getGeminiProcessingError());
	}

	public function testEnqueueGeminiProcessingSkipsAlreadyProcessedRun(): void
	{
		$run = new StockAiAnalysisRun(
			'Generated prompt text',
			true,
			true,
			false,
			null,
			new ImmutableDateTime('2026-05-02 10:00:00'),
		);
		$run->setResponse(
			'{}',
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
			null,
			new ImmutableDateTime('2026-05-02 11:00:00'),
		);

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);
		$this->entityManager->shouldNotReceive('flush');
		$this->jobRequestFacade->shouldNotReceive('addStockAiAnalysisGeminiProcessToQueue');

		$this->facade->enqueueGeminiProcessing($run->getId()->toString());

		self::assertNull($run->getGeminiProcessingStatus());
	}

	public function testCreateRunWithDailyBriefPromptType(): void
	{
		$now = new ImmutableDateTime();

		$this->promptGenerator->shouldReceive('generate')
			->with(
				true,
				true,
				true,
				StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
				null,
				null,
			)
			->once()
			->andReturn('Generated daily brief prompt');

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once();

		$this->entityManager->shouldReceive('flush')
			->once();

		$run = $this->facade->createRun(
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
		);

		self::assertSame('Generated daily brief prompt', $run->getGeneratedPrompt());
		self::assertSame(StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF, $run->getPortfolioPromptType());
		self::assertTrue($run->isDailyBrief());
	}

	public function testProcessResponseWithMarketOverview(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, true, true, null, $now);

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
				'geopoliticalContext' => 'Napětí na Blízkém východě tlačí ceny ropy nahoru',
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertSame($response, $run->getRawResponse());
		self::assertSame('Trhy jsou stabilní', $run->getMarketOverviewSummary());
		self::assertSame(StockAiAnalysisMarketSentimentEnum::BULLISH, $run->getMarketOverviewSentiment());
		self::assertSame(
			'Napětí na Blízkém východě tlačí ceny ropy nahoru',
			$run->getMarketOverviewGeopoliticalContext(),
		);
		self::assertSame($now, $run->getProcessedAt());
	}

	public function testProcessResponseWithPortfolioAnalysis(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, null, $now);
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
					'earningsCommentary' => 'Výsledky překonaly očekávání',
					'dividendAnalysis' => 'Stabilní dividenda s 10letou historií zvyšování',
					'performance7DaysComment' => 'Akcie vzrostla o 3% díky silným výsledkům',
					'confidenceLevel' => 'high',
					'fairPrice' => 150.50,
					'fairPriceCurrency' => 'USD',
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
		self::assertSame('Výsledky překonaly očekávání', $result->getEarningsCommentary());
		self::assertSame('Stabilní dividenda s 10letou historií zvyšování', $result->getDividendAnalysis());
		self::assertSame('Akcie vzrostla o 3% díky silným výsledkům', $result->getPerformance7DaysComment());
		self::assertSame(StockAiAnalysisConfidenceLevelEnum::HIGH, $result->getConfidenceLevel());
		self::assertSame(150.50, $result->getFairPrice());
		self::assertSame(CurrencyEnum::USD, $result->getFairPriceCurrency());
	}

	public function testProcessResponseWithDailyBrief(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun(
			'prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			$now,
		);
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
			'dailyBrief' => [
				'summary' => 'Trh byl dnes nervózní, ale bez strukturální změny.',
				'marketPulse' => 'Technologie reagovaly na pohyb výnosů dluhopisů.',
				'portfolioImpactSummary' => 'Největší pohyb byl u růstových titulů.',
				'watchlistSummary' => 'Na watchlistu stojí za pozornost dvě akcie po výsledcích.',
				'importantAlerts' => 'Sleduj blížící se CPI a earnings velkých techů.',
				'nextDaysChecklist' => 'Zkontroluj výsledky a ponech si hotovost na případný dip.',
				'actionNeeded' => 'monitor',
			],
			'portfolioAnalysis' => [
				[
					'stockAssetId' => $stockAssetId->toString(),
					'positiveNews' => 'Silná reakce po výsledcích.',
					'actionSuggestion' => 'watch_closely',
					'performance1DayComment' => 'Akcie během dne výrazně kolísala kvůli reakci na guidance.',
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertSame('Trh byl dnes nervózní, ale bez strukturální změny.', $run->getDailyBriefSummary());
		self::assertSame('Technologie reagovaly na pohyb výnosů dluhopisů.', $run->getDailyBriefMarketPulse());
		self::assertSame('Největší pohyb byl u růstových titulů.', $run->getDailyBriefPortfolioImpactSummary());
		self::assertSame(
			'Na watchlistu stojí za pozornost dvě akcie po výsledcích.',
			$run->getDailyBriefWatchlistSummary(),
		);
		self::assertSame('Sleduj blížící se CPI a earnings velkých techů.', $run->getDailyBriefImportantAlerts());
		self::assertSame(
			'Zkontroluj výsledky a ponech si hotovost na případný dip.',
			$run->getDailyBriefNextDaysChecklist(),
		);
		self::assertSame(StockAiAnalysisDailyBriefActionNeededEnum::MONITOR, $run->getDailyBriefActionNeeded());
		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertSame(
			'Akcie během dne výrazně kolísala kvůli reakci na guidance.',
			$result->getPerformance1DayComment(),
		);
	}

	public function testProcessResponseWithWatchlistAnalysis(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', false, true, false, null, $now);
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
					'fairPrice' => 200.0,
					'fairPriceCurrency' => 'EUR',
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
		self::assertSame(200.0, $result->getFairPrice());
		self::assertSame(CurrencyEnum::EUR, $result->getFairPriceCurrency());
	}

	public function testProcessResponsePortfolioWithoutFairPrice(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, null, $now);
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
					'actionSuggestion' => 'hold',
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertNull($result->getFairPrice());
		self::assertNull($result->getFairPriceCurrency());
	}

	public function testProcessResponseInvalidJson(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, true, true, null, $now);

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
		$run = new StockAiAnalysisRun('prompt', true, false, false, null, $now);

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
		$run = new StockAiAnalysisRun('prompt', false, false, false, null, $now, 'AAPL', 'Apple Inc.');

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
				'dividendAnalysis' => 'Company pays steady quarterly dividend',
				'earningsCommentary' => 'Beat estimates by 5%',
				'confidenceLevel' => 'medium',
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
		self::assertSame('Company pays steady quarterly dividend', $result->getDividendAnalysis());
		self::assertSame('Beat estimates by 5%', $result->getEarningsCommentary());
		self::assertSame(
			StockAiAnalysisConfidenceLevelEnum::MEDIUM,
			$result->getConfidenceLevel(),
		);
	}

	public function testProcessResponsePortfolioWithDividendAnalysisNull(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, null, $now);
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
					'actionSuggestion' => 'hold',
				],
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertCount(1, $run->getResults());
		$result = $run->getResults()->first();
		self::assertNotFalse($result);
		self::assertNull($result->getDividendAnalysis());
		self::assertNull($result->getPerformance7DaysComment());
		self::assertNull($result->getEarningsCommentary());
		self::assertNull($result->getConfidenceLevel());
	}

	public function testProcessResponseWithPortfolioEvaluation7DaysSummary(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('prompt', true, false, false, null, $now);

		$this->stockAiAnalysisRunRepository->shouldReceive('getById')
			->once()
			->andReturn($run);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('flush')
			->once();

		$response = Json::encode([
			'portfolioEvaluation' => [
				'summary' => 'Portfolio je diverzifikované',
				'performance7DaysSummary' => 'Portfolio vzrostlo o 2.5% za posledních 7 dní',
			],
		]);

		$this->facade->processResponse($run->getId()->toString(), $response);

		self::assertSame('Portfolio je diverzifikované', $run->getPortfolioEvaluationSummary());
		self::assertSame(
			'Portfolio vzrostlo o 2.5% za posledních 7 dní',
			$run->getPortfolioPerformance7DaysSummary(),
		);
	}

}
