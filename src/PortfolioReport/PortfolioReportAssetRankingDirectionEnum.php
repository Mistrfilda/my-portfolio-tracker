<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

enum PortfolioReportAssetRankingDirectionEnum: string
{

	case WINNER = 'winner';

	case LOSER = 'loser';

	public function format(): string
	{
		return match ($this) {
			self::WINNER => 'Winner',
			self::LOSER => 'Loser',
		};
	}

}
