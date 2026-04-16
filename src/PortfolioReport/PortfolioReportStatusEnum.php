<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

enum PortfolioReportStatusEnum: string
{

	case PENDING = 'pending';

	case PROCESSING = 'processing';

	case DONE = 'done';

	case FAILED = 'failed';

	public function format(): string
	{
		return match ($this) {
			self::PENDING => 'Pending',
			self::PROCESSING => 'Processing',
			self::DONE => 'Done',
			self::FAILED => 'Failed',
		};
	}

}
