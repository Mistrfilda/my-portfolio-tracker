<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisActionSuggestionEnum: string
{

	case HOLD = 'hold';
	case CONSIDER_SELLING = 'consider_selling';
	case ADD_MORE = 'add_more';
	case WATCH_CLOSELY = 'watch_closely';
	case CONSIDER_BUYING = 'consider_buying';
	case WAIT = 'wait';
	case NOT_INTERESTING = 'not_interesting';

}
