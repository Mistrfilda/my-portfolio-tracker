<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceSummary
{

	public function __construct(
		private readonly ImmutableDateTime $startDate,
		private readonly ImmutableDateTime $endDate,
		private readonly float $timeWeightedReturn,
		private readonly float|null $annualizedTimeWeightedReturn,
		private readonly float|null $moneyWeightedReturn,
		private readonly float|null $xirr,
	)
	{
	}

	public function getStartDate(): ImmutableDateTime
	{
		return $this->startDate;
	}

	public function getEndDate(): ImmutableDateTime
	{
		return $this->endDate;
	}

	public function getTimeWeightedReturn(): float
	{
		return $this->timeWeightedReturn;
	}

	public function getAnnualizedTimeWeightedReturn(): float|null
	{
		return $this->annualizedTimeWeightedReturn;
	}

	public function getMoneyWeightedReturn(): float|null
	{
		return $this->moneyWeightedReturn;
	}

	public function getXirr(): float|null
	{
		return $this->xirr;
	}

}
