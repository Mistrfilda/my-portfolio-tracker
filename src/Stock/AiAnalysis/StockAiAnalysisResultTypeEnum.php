<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisResultTypeEnum: string
{

	case PORTFOLIO = 'portfolio';
	case WATCHLIST = 'watchlist';

}
