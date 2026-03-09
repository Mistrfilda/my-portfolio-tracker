<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisDailyBriefActionNeededEnum: string
{

	case NONE = 'none';
	case MONITOR = 'monitor';
	case REVIEW_POSITIONS = 'review_positions';
	case REVIEW_WATCHLIST = 'review_watchlist';

}
