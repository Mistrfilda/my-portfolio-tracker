<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPromptGenerator;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Price\StockAssetPriceRecordRepository;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Test\UpdatedTestCase;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisPromptGeneratorTest extends TestCase
{

	public function testGenerateDailyBriefPrompt(): void
	{
		$generator = $this->createGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			$assetPriceSummaryFacade,
			$stockPositionFacade,
			$stockAssetPriceRecordRepository,
			$datetimeFactory,
		);

		$now = new ImmutableDateTime('2026-03-09 09:58:00');
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$stockAssetRepository->shouldReceive('findAll')
			->twice()
			->andReturn([]);

		$assetPriceSummaryFacade->shouldReceive('getCurrentValue')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn($summaryPrice);

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
			$assetPriceSummaryFacade,
		);

		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$stockAssetRepository->shouldReceive('findAll')
			->twice()
			->andReturn([]);

		$assetPriceSummaryFacade->shouldReceive('getCurrentValue')
			->once()
			->with(CurrencyEnum::CZK)
			->andReturn($summaryPrice);

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

	private function createGenerator(
		mixed &$stockAssetRepository = null,
		mixed &$stockValuationDataRepository = null,
		mixed &$assetPriceSummaryFacade = null,
		mixed &$stockPositionFacade = null,
		mixed &$stockAssetPriceRecordRepository = null,
		mixed &$datetimeFactory = null,
	): StockAiAnalysisPromptGenerator
	{
		$stockAssetRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$stockValuationDataRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(
			StockValuationDataRepository::class,
		);
		$assetPriceSummaryFacade ??= UpdatedTestCase::createMockWithIgnoreMethods(AssetPriceSummaryFacade::class);
		$stockPositionFacade ??= UpdatedTestCase::createMockWithIgnoreMethods(StockPositionFacade::class);
		$stockAssetPriceRecordRepository ??= UpdatedTestCase::createMockWithIgnoreMethods(
			StockAssetPriceRecordRepository::class,
		);
		$datetimeFactory ??= UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		return new StockAiAnalysisPromptGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			$assetPriceSummaryFacade,
			$stockPositionFacade,
			$stockAssetPriceRecordRepository,
			$datetimeFactory,
		);
	}

}
