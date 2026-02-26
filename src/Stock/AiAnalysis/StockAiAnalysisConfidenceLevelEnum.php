<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisConfidenceLevelEnum: string
{

	case LOW = 'low';
	case MEDIUM = 'medium';
	case HIGH = 'high';

}
