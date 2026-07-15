<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

enum PortfolioPeriodStatisticStatusEnum: string
{

	case QUEUED = 'queued';

	case PROCESSING = 'processing';

	case COMPLETED = 'completed';

	case FAILED = 'failed';

	public function format(): string
	{
		return match ($this) {
			self::QUEUED => 'Ve frontě',
			self::PROCESSING => 'Zpracovává se',
			self::COMPLETED => 'Dokončeno',
			self::FAILED => 'Selhalo',
		};
	}

}
