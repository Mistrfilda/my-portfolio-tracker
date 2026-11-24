<?php

declare(strict_types = 1);

namespace App\Tax;

enum TaxYearReportStatusEnum: string
{

	case QUEUED = 'queued';

	case PROCESSING = 'processing';

	case COMPLETED = 'completed';

	case FAILED = 'failed';

	public function format(): string
	{
		return match ($this) {
			self::QUEUED => 'Čeká ve frontě',
			self::PROCESSING => 'Zpracovává se',
			self::COMPLETED => 'Hotovo',
			self::FAILED => 'Chyba',
		};
	}

}
