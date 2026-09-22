<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Currency\CurrencyEnum;
use App\Stock\AiAnalysis\StockAiAnalysisActionSuggestionEnum;
use App\Stock\AiAnalysis\StockAiAnalysisConfidenceLevelEnum;
use App\Stock\AiAnalysis\StockAiAnalysisDailyBriefActionNeededEnum;
use App\Stock\AiAnalysis\StockAiAnalysisFollowUpPromptGenerator;
use App\Stock\AiAnalysis\StockAiAnalysisMarketSentimentEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisResultTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use App\Stock\AiAnalysis\StockAiAnalysisSettingsFacade;
use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisFollowUpPromptGeneratorTest extends TestCase
{

	#[DataProvider('provideOriginalPreferences')]
	public function testV2FollowUpUsesCurrentPreferencesWithoutChangingParentSnapshot(string $originalPreferences): void
	{
		$settings = $this->createStub(StockAiAnalysisSettingsFacade::class);
		$settings->method('getInvestorInstructions')->willReturn('Keep Czech holdings.');
		$snapshot = ['schemaVersion' => 2, 'investorInstructions' => $originalPreferences];
		$run = new StockAiAnalysisRun(
			'Original prompt',
			true,
			false,
			false,
			null,
			new ImmutableDateTime('2026-09-20 10:00:00'),
			analysisSchemaVersion: 2,
			inputSnapshot: $snapshot,
		);

		$prompt = (new StockAiAnalysisFollowUpPromptGenerator($settings))->generate($run, 'What should I hold?');

		self::assertSame(1, substr_count($prompt, 'Keep Czech holdings.'));
		self::assertSame(1, substr_count($prompt, 'Investor instructions:'));
		self::assertStringNotContainsString('Original preferences', $prompt);
		self::assertStringContainsString('supersede any older investor preferences', $prompt);
		self::assertSame($snapshot, $run->getInputSnapshot());
	}

	/** @return array<string, array{string}> */
	public static function provideOriginalPreferences(): array
	{
		return [
			'changed' => ['Original preferences'],
			'unchanged' => ['Keep Czech holdings.'],
		];
	}

	public function testGeneratesPromptWithOriginalAnalysisContext(): void
	{
		$now = new ImmutableDateTime('2026-05-24 10:00:00');
		$run = new StockAiAnalysisRun(
			'Original generated prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			$now,
			'AAPL',
			'Apple Inc.',
		);
		$run->setResponse(
			'Raw original response',
			'Market overview summary',
			StockAiAnalysisMarketSentimentEnum::BULLISH,
			'Geopolitical context',
			'Portfolio evaluation summary',
			'7 day performance summary',
			'Daily brief summary',
			'Market pulse',
			'Portfolio impact summary',
			'Watchlist summary',
			'Important alerts',
			'Next days checklist',
			StockAiAnalysisDailyBriefActionNeededEnum::MONITOR,
			$now,
		);
		$result = new StockAiAnalysisStockResult(
			$run,
			null,
			StockAiAnalysisResultTypeEnum::PORTFOLIO,
			'Positive news',
			'Negative news',
			'Interesting news',
			'AI opinion',
			StockAiAnalysisActionSuggestionEnum::HOLD,
			'Reasoning',
			'News',
			'AAPL',
			'Apple Inc.',
			'Business summary',
			'Moat analysis',
			'Financial health',
			'Growth catalysts',
			'Valuation assessment',
			'Conclusion',
			'Risks',
			'Earnings commentary',
			'Dividend analysis',
			'Performance 7 days',
			'Performance 1 day',
			StockAiAnalysisConfidenceLevelEnum::HIGH,
			123.45,
			CurrencyEnum::USD,
			$now,
		);
		$run->addResult($result);

		$prompt = (new StockAiAnalysisFollowUpPromptGenerator(
			$this->createStub(StockAiAnalysisSettingsFacade::class),
		))->generate($run, 'What are the biggest risks now?');

		self::assertStringContainsString('Kontext původní analýzy:', $prompt);
		self::assertStringContainsString('Original generated prompt', $prompt);
		self::assertStringContainsString('Raw original response', $prompt);
		self::assertStringContainsString('Market overview summary', $prompt);
		self::assertStringContainsString('Portfolio evaluation summary', $prompt);
		self::assertStringContainsString('AAPL', $prompt);
		self::assertStringContainsString('AI opinion', $prompt);
		self::assertStringContainsString('What are the biggest risks now?', $prompt);
	}

}
