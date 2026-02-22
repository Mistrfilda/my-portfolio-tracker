<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis;

use App\Stock\AiAnalysis\StockAiAnalysisMarketSentimentEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisRunTest extends TestCase
{

	public function testCreate(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('Test prompt', true, false, true, $now);

		self::assertSame('Test prompt', $run->getGeneratedPrompt());
		self::assertTrue($run->includesPortfolio());
		self::assertFalse($run->includesWatchlist());
		self::assertTrue($run->includesMarketOverview());
		self::assertNull($run->getRawResponse());
		self::assertNull($run->getMarketOverviewSummary());
		self::assertNull($run->getMarketOverviewSentiment());
		self::assertNull($run->getProcessedAt());
		self::assertCount(0, $run->getResults());
	}

	public function testSetResponse(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('Test prompt', true, true, true, $now);

		$processedAt = new ImmutableDateTime();
		$run->setResponse(
			'{"test": "response"}',
			'Trh je stabilní',
			StockAiAnalysisMarketSentimentEnum::BULLISH,
			'Portfolio je diverzifikované',
			$processedAt,
		);

		self::assertSame('{"test": "response"}', $run->getRawResponse());
		self::assertSame('Trh je stabilní', $run->getMarketOverviewSummary());
		self::assertSame(StockAiAnalysisMarketSentimentEnum::BULLISH, $run->getMarketOverviewSentiment());
		self::assertSame('Portfolio je diverzifikované', $run->getPortfolioEvaluationSummary());
		self::assertSame($processedAt, $run->getProcessedAt());
	}

	public function testSetResponseWithNullMarketOverview(): void
	{
		$now = new ImmutableDateTime();
		$run = new StockAiAnalysisRun('Test prompt', true, false, false, $now);

		$processedAt = new ImmutableDateTime();
		$run->setResponse('{"test": "response"}', null, null, null, $processedAt);

		self::assertSame('{"test": "response"}', $run->getRawResponse());
		self::assertNull($run->getMarketOverviewSummary());
		self::assertNull($run->getMarketOverviewSentiment());
		self::assertNull($run->getPortfolioEvaluationSummary());
		self::assertSame($processedAt, $run->getProcessedAt());
	}

}
