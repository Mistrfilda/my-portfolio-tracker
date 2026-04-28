<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\ActionChecklist;

use App\Stock\AiAnalysis\StockAiAnalysisDailyBriefActionNeededEnum;
use App\Stock\AiAnalysis\StockAiAnalysisRun;

class StockAiAnalysisActionChecklistProvider
{

	/**
	 * @return array<int, StockAiAnalysisActionChecklistItem>
	 */
	public function getForRun(StockAiAnalysisRun $run): array
	{
		$actionNeeded = $run->getDailyBriefActionNeeded();
		if ($run->getDailyBriefNextDaysChecklist() === null && $actionNeeded === null) {
			return [];
		}

		$priority = $this->getPriority($actionNeeded);
		$items = [];
		foreach ($this->splitChecklistText($run->getDailyBriefNextDaysChecklist()) as $itemText) {
			$items[] = new StockAiAnalysisActionChecklistItem($itemText, $priority);
		}

		if ($items !== []) {
			return $items;
		}

		return [new StockAiAnalysisActionChecklistItem($this->getFallbackText($actionNeeded), $priority)];
	}

	/**
	 * @return array<int, string>
	 */
	private function splitChecklistText(string|null $text): array
	{
		if ($text === null || trim($text) === '') {
			return [];
		}

		$parts = preg_split('/(?:\r?\n|(?:^|\s)(?:[-*•]|\d+[.)])\s+)/u', $text);
		if ($parts === false) {
			$parts = [];
		}

		if (count($parts) <= 1) {
			$parts = preg_split('/(?<=[.!?])\s+/u', $text);
			if ($parts === false) {
				$parts = [];
			}
		}

		$items = [];
		foreach ($parts as $part) {
			$item = trim($part, " \t\n\r\0\x0B-•*");
			if ($item === '') {
				continue;
			}

			$items[] = $item;
			if (count($items) >= 5) {
				break;
			}
		}

		return $items;
	}

	private function getPriority(
		StockAiAnalysisDailyBriefActionNeededEnum|null $actionNeeded,
	): StockAiAnalysisActionChecklistPriorityEnum
	{
		return match ($actionNeeded) {
			StockAiAnalysisDailyBriefActionNeededEnum::REVIEW_POSITIONS,
			StockAiAnalysisDailyBriefActionNeededEnum::REVIEW_WATCHLIST => StockAiAnalysisActionChecklistPriorityEnum::HIGH,
			StockAiAnalysisDailyBriefActionNeededEnum::MONITOR => StockAiAnalysisActionChecklistPriorityEnum::MEDIUM,
			default => StockAiAnalysisActionChecklistPriorityEnum::LOW,
		};
	}

	private function getFallbackText(StockAiAnalysisDailyBriefActionNeededEnum|null $actionNeeded): string
	{
		return match ($actionNeeded) {
			StockAiAnalysisDailyBriefActionNeededEnum::REVIEW_POSITIONS => 'Review portfolio positions highlighted by the daily brief.',
			StockAiAnalysisDailyBriefActionNeededEnum::REVIEW_WATCHLIST => 'Review watchlist candidates highlighted by the daily brief.',
			StockAiAnalysisDailyBriefActionNeededEnum::MONITOR => 'Monitor the situation during the next 1–3 days.',
			default => 'No active action is needed; keep the daily brief for reference.',
		};
	}

}
