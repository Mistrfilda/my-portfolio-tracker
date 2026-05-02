<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

enum StockAiAnalysisGeminiProcessingStatusEnum: string
{

	case QUEUED = 'queued';

	case PROCESSING = 'processing';

	case COMPLETED = 'completed';

	case FAILED = 'failed';

}
