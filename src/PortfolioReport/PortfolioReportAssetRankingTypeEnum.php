<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

enum PortfolioReportAssetRankingTypeEnum: string
{

	case PRICE = 'price';

	case CONTRIBUTION = 'contribution';

	public function format(): string
	{
		return match ($this) {
			self::PRICE => 'Price',
			self::CONTRIBUTION => 'Contribution',
		};
	}

}
