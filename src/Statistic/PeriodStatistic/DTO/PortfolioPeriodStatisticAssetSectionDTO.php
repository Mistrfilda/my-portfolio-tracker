<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\DTO;

class PortfolioPeriodStatisticAssetSectionDTO
{

	/**
	 * @param array<PortfolioPeriodStatisticAssetDTO> $assets
	 * @param array<string> $warnings
	 */
	public function __construct(
		public array $assets,
		public array $warnings = [],
	)
	{
	}

}
