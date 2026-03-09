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
		$stockAssetRepository = UpdatedTestCase::createMockWithIgnoreMethods(StockAssetRepository::class);
		$stockValuationDataRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockValuationDataRepository::class,
		);
		$assetPriceSummaryFacade = UpdatedTestCase::createMockWithIgnoreMethods(AssetPriceSummaryFacade::class);
		$stockPositionFacade = UpdatedTestCase::createMockWithIgnoreMethods(StockPositionFacade::class);
		$stockAssetPriceRecordRepository = UpdatedTestCase::createMockWithIgnoreMethods(
			StockAssetPriceRecordRepository::class,
		);
		$datetimeFactory = UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class);

		$generator = new StockAiAnalysisPromptGenerator(
			$stockAssetRepository,
			$stockValuationDataRepository,
			$assetPriceSummaryFacade,
			$stockPositionFacade,
			$stockAssetPriceRecordRepository,
			$datetimeFactory,
		);

		$now = new ImmutableDateTime('2026-03-09 09:58:00');
		$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);

		$datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

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
	}

}
