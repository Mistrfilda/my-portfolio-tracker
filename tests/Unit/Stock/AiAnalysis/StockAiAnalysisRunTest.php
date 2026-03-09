<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisMarketSentimentEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisRunTest extends TestCase
{

	public function testCreate(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('Test prompt', true, false, true, null, $now);

		self::assertSame('Test prompt', $run->getGeneratedPrompt());
		self::assertTrue($run->includesPortfolio());
		self::assertFalse($run->includesWatchlist());
		self::assertTrue($run->includesMarketOverview());
		self::assertNull($run->getPortfolioPromptType());
		self::assertNull($run->getRawResponse());
		self::assertNull($run->getMarketOverviewSummary());
		self::assertNull($run->getMarketOverviewSentiment());
		self::assertNull($run->getProcessedAt());
		self::assertCount(0, $run->getResults());
	}

	public function testSetResponse(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun(
			'Test prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			$now,
		);

		$processedAt = new ImmutableDateTime();
		$run->setResponse(
			'{"test": "response"}',
			'Trh je stabilní',
			StockAiAnalysisMarketSentimentEnum::BULLISH,
			'Portfolio je diverzifikované',
			'Portfolio za 7 dní vzrostlo',
			'Denní briefing shrnutí',
			'Trhem hýbaly výsledky technologických firem',
			'Portfolio reagovalo hlavně přes růstové tituly',
			'Watchlist nabízí dvě zajímavé příležitosti',
			'Sleduj výnosy dluhopisů',
			'Projdi earnings kalendář na další dva dny',
			null,
			$processedAt,
		);

		self::assertSame('{"test": "response"}', $run->getRawResponse());
		self::assertSame('Trh je stabilní', $run->getMarketOverviewSummary());
		self::assertSame(StockAiAnalysisMarketSentimentEnum::BULLISH, $run->getMarketOverviewSentiment());
		self::assertSame('Portfolio je diverzifikované', $run->getPortfolioEvaluationSummary());
		self::assertSame('Portfolio za 7 dní vzrostlo', $run->getPortfolioPerformance7DaysSummary());
		self::assertSame(StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF, $run->getPortfolioPromptType());
		self::assertTrue($run->isDailyBrief());
		self::assertSame('Denní briefing shrnutí', $run->getDailyBriefSummary());
		self::assertSame('Trhem hýbaly výsledky technologických firem', $run->getDailyBriefMarketPulse());
		self::assertSame(
			'Portfolio reagovalo hlavně přes růstové tituly',
			$run->getDailyBriefPortfolioImpactSummary(),
		);
		self::assertSame('Watchlist nabízí dvě zajímavé příležitosti', $run->getDailyBriefWatchlistSummary());
		self::assertSame('Sleduj výnosy dluhopisů', $run->getDailyBriefImportantAlerts());
		self::assertSame('Projdi earnings kalendář na další dva dny', $run->getDailyBriefNextDaysChecklist());
		self::assertSame($processedAt, $run->getProcessedAt());
	}

	public function testSetResponseWithNullMarketOverview(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('Test prompt', true, false, false, null, $now);

		$processedAt = new ImmutableDateTime();
		$run->setResponse(
			'{"test": "response"}',
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
			$processedAt,
		);

		self::assertSame('{"test": "response"}', $run->getRawResponse());
		self::assertNull($run->getMarketOverviewSummary());
		self::assertNull($run->getMarketOverviewSentiment());
		self::assertNull($run->getPortfolioEvaluationSummary());
		self::assertNull($run->getPortfolioPerformance7DaysSummary());
		self::assertNull($run->getDailyBriefSummary());
		self::assertFalse($run->isDailyBrief());
		self::assertSame($processedAt, $run->getProcessedAt());
	}

}
