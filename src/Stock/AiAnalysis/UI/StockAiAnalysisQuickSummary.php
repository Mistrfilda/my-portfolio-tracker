<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\UI;

use App\Stock\AiAnalysis\StockAiAnalysisStockResult;
use Nette\Utils\Strings;

class StockAiAnalysisQuickSummary
{

	public static function fromResult(StockAiAnalysisStockResult $result): string
	{
		foreach ([$result->getReasoning(), $result->getAiOpinion(), $result->getConclusion()] as $text) {
			if ($text === null || trim($text) === '') {
				continue;
			}

			$text = Strings::replace(trim($text), '~\s+~u', ' ');
			$summary = '';
			foreach (Strings::split($text, '~(?<=[.!?])\s+(?=\p{Lu})~u') as $part) {
				$summary .= ($summary === '' ? '' : ' ') . $part;
				if (Strings::match(
					$part,
					'~\b(?:(?i:mil|mld|tis|např|tj|tzn|resp|cca|atd|atp|inc|corp|ltd|co)|\p{Lu})\.$~u',
				) === null) {
					break;
				}
			}

			return $summary;
		}

		return 'Stručné hodnocení není k dispozici.';
	}

}
