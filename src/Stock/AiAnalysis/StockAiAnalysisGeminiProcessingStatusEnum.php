<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\UI\Control\Datagrid\Column\DatagridRenderableEnum;

enum StockAiAnalysisGeminiProcessingStatusEnum: string implements DatagridRenderableEnum
{

	case QUEUED = 'queued';

	case PROCESSING = 'processing';

	case COMPLETED = 'completed';

	case FAILED = 'failed';

	public function format(): string
	{
		return match ($this) {
			self::QUEUED => 'Queued',
			self::PROCESSING => 'Processing',
			self::COMPLETED => 'Completed',
			self::FAILED => 'Failed',
		};
	}

}
