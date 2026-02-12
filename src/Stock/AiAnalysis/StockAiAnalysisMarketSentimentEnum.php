<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisMarketSentimentEnum: string
{

	case BULLISH = 'bullish';
	case BEARISH = 'bearish';
	case NEUTRAL = 'neutral';

}
