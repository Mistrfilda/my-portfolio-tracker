<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetDetailDTO;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockAssetPositionDetailDTO;
use App\Stock\Position\StockPosition;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class StockAiAnalysisPromptGeneratorTest extends TestCase
{

	public function testGenerateDailyBriefPrompt(): void
	{
		$generator = $this->createGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			stockPositionFacade: $stockPositionFacade,
			stockAssetPriceRecordRepository: $stockAssetPriceRecordRepository,
			datetimeFactory: $datetimeFactory,
		);

		$now = new ImmutableDateTime('2026-03-09 09:58:00');

		$stockAssetRepository->shouldReceive('findAll')
			->twice()
			->andReturn([]);

		$prompt = $generator->generate(
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
		);

		self::assertStringContainsString('Připrav denní briefing za posledních 24 hodin.', $prompt);
		self::assertStringContainsString('performance1DayComment', $prompt);
		self::assertStringContainsString('"dailyBrief"', $prompt);
		self::assertStringContainsString('"portfolio": []', $prompt);
		self::assertStringContainsString('"watchlist": []', $prompt);
		self::assertStringNotContainsString('Jsi zkušený finanční analytik', $prompt);

		$datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		self::assertStringContainsString('09. 03. 2026', $generator->generateSystemInstruction());
	}

	public function testGenerateStockAnalysisWithMarketOverview(): void
	{
		$generator = $this->createGenerator();

		$prompt = $generator->generate(
			false,
			false,
			true,
			null,
			'AAPL',
			'Apple Inc.',
		);

		self::assertStringContainsString('Analyzuj aktuální situaci na trhu', $prompt);
		self::assertStringContainsString('Analyzuj detailně společnost Apple Inc. (AAPL).', $prompt);
		self::assertStringContainsString('"marketOverview"', $prompt);
		self::assertStringContainsString('"stockAnalysis"', $prompt);
		self::assertStringContainsString('"fairPriceCurrency"', $prompt);
		self::assertStringNotContainsString('"portfolioAnalysis"', $prompt);
		self::assertStringNotContainsString('"watchlistAnalysis"', $prompt);
		self::assertStringNotContainsString('Data k analýze:\n{', $prompt);
	}

	public function testGeneratePortfolioAndWatchlistPromptUsesWeeklyPerformanceSchema(): void
	{
		$generator = $this->createGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
		);

		$stockAssetRepository->shouldReceive('findAll')
			->twice()
			->andReturn([]);

		$prompt = $generator->generate(true, true, false);

		self::assertStringContainsString('performance7DaysComment', $prompt);
		self::assertStringContainsString('"portfolioEvaluation"', $prompt);
		self::assertStringContainsString('"portfolioAnalysis"', $prompt);
		self::assertStringContainsString('"watchlistAnalysis"', $prompt);
		self::assertStringContainsString('"sectorAllocation": []', $prompt);
		self::assertStringContainsString('"totalPositions": 0', $prompt);
		self::assertStringNotContainsString('performance1DayComment', $prompt);
		self::assertStringNotContainsString('"dailyBrief"', $prompt);
	}

	public function testGenerateAutomaticPortfolioStockPromptUsesDailySchema(): void
	{
		$generator = $this->createGenerator();

		$prompt = $generator->generateAutomaticPortfolioStockPrompt(
			[
				'stockAssetId' => 'asset-1',
				'stockAssetName' => 'Apple Inc.',
				'stockAssetTicker' => 'AAPL',
			],
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
		);

		self::assertStringContainsString('"portfolioAnalysis"', $prompt);
		self::assertStringContainsString('"performance1DayComment"', $prompt);
		self::assertStringNotContainsString('"performance7DaysComment"', $prompt);
		self::assertStringContainsString('"stockAssetId": "asset-1"', $prompt);
		self::assertStringContainsString('"stockAssetTicker": "AAPL"', $prompt);
	}

	public function testGenerateAutomaticWatchlistStockPromptUsesWeeklySchema(): void
	{
		$generator = $this->createGenerator();

		$prompt = $generator->generateAutomaticWatchlistStockPrompt(
			[
				'stockAssetId' => 'asset-2',
				'stockAssetName' => 'Microsoft',
				'stockAssetTicker' => 'MSFT',
			],
			null,
		);

		self::assertStringContainsString('"watchlistAnalysis"', $prompt);
		self::assertStringContainsString('"performance7DaysComment"', $prompt);
		self::assertStringNotContainsString('"performance1DayComment"', $prompt);
		self::assertStringContainsString('"buyRecommendation"', $prompt);
		self::assertStringContainsString('"stockAssetTicker": "MSFT"', $prompt);
	}

	public function testGenerateAutomaticReducePromptBuildsSummaryOnlySchema(): void
	{
		$generator = $this->createGenerator();

		$prompt = $generator->generateAutomaticReducePrompt(
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			[
				[
					'stockAssetTicker' => 'AAPL',
					'aiOpinion' => 'Strong business quality.',
				],
			],
			[
				[
					'stockAssetTicker' => 'MSFT',
					'news' => 'Cloud growth remains relevant.',
				],
			],
		);

		self::assertStringContainsString('"marketOverview"', $prompt);
		self::assertStringContainsString('"dailyBrief"', $prompt);
		self::assertStringContainsString('"portfolioAnalysis"', $prompt);
		self::assertStringContainsString('"watchlistAnalysis"', $prompt);
		self::assertStringContainsString('"stockAssetTicker": "AAPL"', $prompt);
		self::assertStringContainsString('"stockAssetTicker": "MSFT"', $prompt);
		self::assertStringNotContainsString('"portfolioEvaluation"', $prompt);
	}

	public function testGenerateManualOpenPositionsPromptContainsOnlyOpenPositionsSummary(): void
	{
		$generator = $this->createGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			stockPositionFacade: $stockPositionFacade,
			stockAssetPriceRecordRepository: $stockAssetPriceRecordRepository,
			datetimeFactory: $datetimeFactory,
		);

		$stockAsset = UpdatedTestCase::createMockWithIgnoreMethods(StockAsset::class);
		$stockAsset->shouldReceive('hasOpenPositions')
			->andReturn(true);
		$stockAsset->shouldReceive('getId')
			->andReturn(Uuid::fromString('4f5874f6-782b-4d92-a8fe-efc3b1b0c8ef'));
		$stockAsset->shouldReceive('getName')
			->andReturn('Apple Inc.');
		$stockAsset->shouldReceive('getTicker')
			->andReturn('AAPL');
		$stockAsset->shouldReceive('getCurrency')
			->andReturn(CurrencyEnum::USD);
		$stockAsset->shouldReceive('getAssetCurrentPrice')
			->andReturn(new AssetPrice($stockAsset, 120, CurrencyEnum::USD));

		$olderPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$olderPosition->shouldReceive('getOrderDate')
			->andReturn(new ImmutableDateTime('2025-01-10'));
		$newerPosition = UpdatedTestCase::createMockWithIgnoreMethods(StockPosition::class);
		$newerPosition->shouldReceive('getOrderDate')
			->andReturn(new ImmutableDateTime('2025-03-15'));

		$positionDtos = [
			new StockAssetPositionDetailDTO($olderPosition, new PriceDiff(0, 100, CurrencyEnum::USD)),
			new StockAssetPositionDetailDTO($newerPosition, new PriceDiff(0, 100, CurrencyEnum::USD)),
		];
		$stockAssetDetail = new StockAssetDetailDTO(
			$stockAsset,
			$positionDtos,
			new SummaryPrice(CurrencyEnum::CZK, 10_000),
			new SummaryPrice(CurrencyEnum::CZK, 12_000),
			new SummaryPrice(CurrencyEnum::USD, 500),
			new SummaryPrice(CurrencyEnum::USD, 450),
			new PriceDiff(2_000, 120, CurrencyEnum::CZK),
			new PriceDiff(50, 111.11, CurrencyEnum::USD),
			new SummaryPrice(CurrencyEnum::CZK, 1_200),
			new PriceDiff(0, 100, CurrencyEnum::CZK),
			10,
		);

		$stockAssetRepository->shouldReceive('findAll')
			->once()
			->andReturn([$stockAsset]);
		$stockPositionFacade->shouldReceive('getStockAssetDetailDTO')
			->once()
			->andReturn($stockAssetDetail);
		$stockValuationDataRepository->shouldReceive('findLatestForStockAsset')
			->andReturn([]);
		$datetimeFactory->shouldReceive('createNow')
			->twice()
			->andReturn(new ImmutableDateTime('2025-04-01'));
		$stockAssetPriceRecordRepository->shouldReceive('findByStockAssetSinceDate')
			->twice()
			->andReturn([]);

		$prompt = $generator->generateManualOpenPositionsPrompt();

		self::assertStringContainsString('"openPositions": [', $prompt);
		self::assertStringContainsString('"stockAssetTicker": "AAPL"', $prompt);
		self::assertStringContainsString('"portfolioPercentage": 100', $prompt);
		self::assertStringContainsString('"profitLossPercent": 20', $prompt);
		self::assertStringContainsString('"lastPurchaseDate": "2025-03-15"', $prompt);
		self::assertStringContainsString('"averagePurchasePrice": 1000', $prompt);
		self::assertStringNotContainsString('"watchlist"', $prompt);
		self::assertStringNotContainsString('"marketOverview"', $prompt);
	}

	public function testGenerateResponseSchemaBuildsGeminiSchema(): void
	{
		$generator = $this->createGenerator();

		$schema = $generator->generateResponseSchema(
			false,
			false,
			true,
			null,
			'AAPL',
			'Apple Inc.',
		);

		self::assertSame('OBJECT', $schema['type']);
		self::assertSame(['marketOverview', 'stockAnalysis'], $schema['required']);
		self::assertSame('OBJECT', $schema['properties']['marketOverview']['type']);
		self::assertSame(
			['summary', 'sentiment', 'geopoliticalContext'],
			$schema['properties']['marketOverview']['required'],
		);
		self::assertSame(
			['bullish', 'bearish', 'neutral'],
			$schema['properties']['marketOverview']['properties']['sentiment']['enum'],
		);
		self::assertSame('NUMBER', $schema['properties']['stockAnalysis']['properties']['fairPrice']['type']);
	}

	public function testGenerateAutomaticPortfolioStockResponseSchemaBuildsGeminiArraySchema(): void
	{
		$generator = $this->createGenerator();

		$schema = $generator->generateAutomaticPortfolioStockResponseSchema(
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
		);

		self::assertSame('OBJECT', $schema['type']);
		self::assertSame(['portfolioAnalysis'], $schema['required']);
		self::assertSame('ARRAY', $schema['properties']['portfolioAnalysis']['type']);
		self::assertSame('OBJECT', $schema['properties']['portfolioAnalysis']['items']['type']);
		self::assertContains(
			'performance1DayComment',
			$schema['properties']['portfolioAnalysis']['items']['required'],
		);
		self::assertNotContains(
			'performance7DaysComment',
			$schema['properties']['portfolioAnalysis']['items']['required'],
		);
		self::assertSame(
			['hold', 'consider_selling', 'add_more', 'watch_closely'],
			$schema['properties']['portfolioAnalysis']['items']['properties']['actionSuggestion']['enum'],
		);
	}

	private function createGenerator(
		mixed &$stockAssetRepository = null,
		mixed &$stockValuationDataRepository = null,
		mixed &$stockPositionFacade = null,
		mixed &$stockAssetPriceRecordRepository = null,
		mixed &$datetimeFactory = null,
	): StockAiAnalysisPromptGenerator
	{
		$stockAssetRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$stockValuationDataRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(
			StockValuationDataRepository::class,
		);
		$stockPositionFacade ??= UpdatedTestCase::createMockWithIgnoreMethods(StockPositionFacade::class);
		$stockAssetPriceRecordRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(
			StockAssetPriceRecordRepository::class,
		);
		$datetimeFactory ??= UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		return new StockAiAnalysisPromptGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			$stockPositionFacade,
			$stockAssetPriceRecordRepository,
			$datetimeFactory,
		);
	}

}
