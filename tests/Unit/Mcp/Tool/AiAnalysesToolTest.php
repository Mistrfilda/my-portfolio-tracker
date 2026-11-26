<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Mcp\Tool\AiAnalysesTool;
use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistProvider;
use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisConfidenceLevelEnum;
use App\Stock\AiAnalysis\StockAiAnalysisDailyBriefActionNeededEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisProcessingSourceEnum;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisRunRepository;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use App\Stock\AiAnalysis\StockAiAnalysisStockResultRepository;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class AiAnalysesToolTest extends TestCase
{

	public function testReturnsV2AndV1RunsWithChecklistWithoutRawPrompts(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$v2Run = new StockAiAnalysisRun(
			$owner,
			'SECRET PROMPT THAT MUST NOT LEAK',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			$now,
			analysisSchemaVersion: 2,
		);
		$v2Run->setV2Response(
			'RAW RESPONSE THAT MUST NOT LEAK',
			[
				'dailyBrief' => [
					'summary' => 'Markets stable.',
					'checklist' => [
						['action' => 'Review MSFT', 'reason' => 'Earnings beat', 'priority' => 'high'],
					],
				],
			],
			StockAiAnalysisProcessingSourceEnum::GEMINI,
			$now,
		);

		$v1Run = new StockAiAnalysisRun(
			$owner,
			'LEGACY PROMPT',
			true,
			false,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now->deductDaysFromDatetime(7),
			analysisSchemaVersion: 1,
		);
		$v1Run->setResponse(
			'LEGACY RAW',
			'Market overview text',
			null,
			null,
			'Portfolio looks solid',
			null,
			null,
			null,
			null,
			null,
			null,
			'Check tech exposure',
			StockAiAnalysisDailyBriefActionNeededEnum::MONITOR,
			$now->deductDaysFromDatetime(7),
		);

		$runRepository = $this->createMock(StockAiAnalysisRunRepository::class);
		$runRepository->expects(self::once())
			->method('findLatestForAppAdmin')
			->with($owner, 'portfolio', 5)
			->willReturn([$v2Run, $v1Run]);

		$tool = new AiAnalysesTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			$runRepository,
			self::createStub(StockAiAnalysisStockResultRepository::class),
			new StockAiAnalysisActionChecklistProvider(),
		);

		$result = $tool->execute(type: 'portfolio', limit: 5);

		self::assertSame(2, $result['runsCount']);
		self::assertIsArray($result['runs']);
		self::assertIsArray($result['runs'][0]);
		self::assertSame(2, $result['runs'][0]['schemaVersion']);
		self::assertArrayNotHasKey('generatedPrompt', $result['runs'][0]);
		self::assertArrayNotHasKey('rawResponse', $result['runs'][0]);
		self::assertSame([
			['text' => 'Review MSFT — Earnings beat', 'priority' => 'high'],
		], $result['runs'][0]['actionChecklist']);

		self::assertIsArray($result['runs'][1]);
		self::assertSame(1, $result['runs'][1]['schemaVersion']);
		self::assertIsArray($result['runs'][1]['summary']);
		self::assertSame('Portfolio looks solid', $result['runs'][1]['summary']['portfolioEvaluationSummary']);
	}

	public function testReturnsPerStockResultsFilteredForOwner(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'Microsoft',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'MSFT',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			true,
			false,
		);

		$run = new StockAiAnalysisRun(
			$owner,
			'PROMPT',
			true,
			false,
			false,
			StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION,
			$now,
			analysisSchemaVersion: 2,
		);
		$stockResult = new StockAiAnalysisStockResult(
			$run,
			$asset,
			StockAiAnalysisResultTypeEnum::PORTFOLIO,
			null,
			null,
			null,
			'Strong cloud moat',
			StockAiAnalysisActionSuggestionEnum::ADD_MORE,
			'Attractive valuation',
			null,
			'MSFT',
			'Microsoft',
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
			StockAiAnalysisConfidenceLevelEnum::HIGH,
			480.0,
			CurrencyEnum::USD,
			$now,
			['marginOfSafetyPercent' => 18.5, 'valuation' => ['fairValueLow' => 450.0, 'fairValueHigh' => 510.0]],
		);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);

		$stockResultRepository = $this->createMock(StockAiAnalysisStockResultRepository::class);
		$stockResultRepository->expects(self::once())
			->method('findLatestForStockAsset')
			->with($asset, 3, $owner)
			->willReturn([$stockResult]);

		$tool = new AiAnalysesTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver($assetRepository),
			self::createStub(StockAiAnalysisRunRepository::class),
			$stockResultRepository,
			new StockAiAnalysisActionChecklistProvider(),
		);

		$result = $tool->execute(ticker: 'MSFT', limit: 3);

		self::assertSame(1, $result['stockResultsCount']);
		self::assertSame(1, $result['runsCount']);
		self::assertIsArray($result['stockResults']);
		self::assertIsArray($result['stockResults'][0]);
		self::assertSame(480.0, $result['stockResults'][0]['fairPrice']);
		self::assertSame(18.5, $result['stockResults'][0]['v2MarginOfSafetyPercent']);
		self::assertSame(450.0, $result['stockResults'][0]['v2FairValueLow']);
		self::assertSame(510.0, $result['stockResults'][0]['v2FairValueHigh']);
	}

	public function testRejectsInvalidType(): void
	{
		$tool = new AiAnalysesTool(
			self::createStub(CurrentAppAdminGetter::class),
			self::createStub(DatetimeFactory::class),
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			self::createStub(StockAiAnalysisRunRepository::class),
			self::createStub(StockAiAnalysisStockResultRepository::class),
			new StockAiAnalysisActionChecklistProvider(),
		);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(type: 'unknown_type');
	}

}
