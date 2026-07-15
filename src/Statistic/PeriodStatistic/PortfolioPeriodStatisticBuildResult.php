<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPeriodStatisticBuildResult
{

	public function __construct(
		public ImmutableDateTime $effectiveStartAt,
		public ImmutableDateTime $effectiveEndAt,
		public PortfolioPeriodStatisticSummaryDTO $summary,
		public PortfolioPeriodStatisticAssetSectionDTO $assetSection,
		public PortfolioPeriodStatisticDividendSectionDTO $dividendSection,
		public PortfolioPeriodStatisticChartSectionDTO $chartSection,
	)
	{
	}

}
