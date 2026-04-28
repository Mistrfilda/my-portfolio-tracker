<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\AiAnalysis\ActionChecklist;

use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistPriorityEnum;
use App\Stock\AiAnalysis\ActionChecklist\StockAiAnalysisActionChecklistProvider;
use App\Stock\AiAnalysis\StockAiAnalysisDailyBriefActionNeededEnum;
use App\Stock\AiAnalysis\StockAiAnalysisPortfolioPromptTypeEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockAiAnalysisActionChecklistProviderTest extends TestCase
{

	public function testBuildsChecklistItemsFromBulletText(): void
	{
		$provider = new StockAiAnalysisActionChecklistProvider();
		$run = $this->createRun(
			"- Check MSFT valuation\n- Review NVDA watchlist price",
			StockAiAnalysisDailyBriefActionNeededEnum::REVIEW_WATCHLIST,
		);

		$items = $provider->getForRun($run);

		self::assertCount(2, $items);
		self::assertSame('Check MSFT valuation', $items[0]->getText());
		self::assertSame('Review NVDA watchlist price', $items[1]->getText());
		self::assertSame(StockAiAnalysisActionChecklistPriorityEnum::HIGH, $items[0]->getPriority());
	}

	public function testReturnsFallbackItemWhenOnlyActionNeededIsAvailable(): void
	{
		$provider = new StockAiAnalysisActionChecklistProvider();
		$run = $this->createRun(null, StockAiAnalysisDailyBriefActionNeededEnum::MONITOR);

		$items = $provider->getForRun($run);

		self::assertCount(1, $items);
		self::assertSame('Monitor the situation during the next 1–3 days.', $items[0]->getText());
		self::assertSame(StockAiAnalysisActionChecklistPriorityEnum::MEDIUM, $items[0]->getPriority());
	}

	public function testReturnsNoItemsForRunWithoutDailyBriefActionData(): void
	{
		$provider = new StockAiAnalysisActionChecklistProvider();
		$run = $this->createRun(null, null);

		self::assertSame([], $provider->getForRun($run));
	}

	private function createRun(
		string|null $nextDaysChecklist,
		StockAiAnalysisDailyBriefActionNeededEnum|null $actionNeeded,
	): StockAiAnalysisRun
	{
		$now = new ImmutableDateTime('2026-01-01');
		$run = new StockAiAnalysisRun(
			'prompt',
			true,
			true,
			true,
			StockAiAnalysisPortfolioPromptTypeEnum::DAILY_BRIEF,
			$now,
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
			$nextDaysChecklist,
			$actionNeeded,
			$now,
		);

		return $run;
	}

}
