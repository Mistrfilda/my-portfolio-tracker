<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;

class PortfolioStatisticTotalValueProvider
{

	public function __construct(
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
	)
	{
	}

	public function getAllTimeValue(): PortfolioStatisticTotalValue|null
	{
		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst();
		$lastRecord = $this->portfolioStatisticRecordRepository->findLast();
		if ($firstRecord === null || $lastRecord === null || $firstRecord === $lastRecord) {
			return null;
		}

		$investedAtStart = $firstRecord->getPortfolioStatisticByType(
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
		);
		$investedAtEnd = $lastRecord->getPortfolioStatisticByType(
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
		);
		$valueAtStart = $firstRecord->getPortfolioStatisticByType(
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
		);
		$valueAtEnd = $lastRecord->getPortfolioStatisticByType(
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
		);

		if (
			$investedAtStart === null
			|| $investedAtEnd === null
			|| $valueAtStart === null
			|| $valueAtEnd === null
		) {
			return null;
		}

		$dailyValues = $this->portfolioStatisticRecordRepository->findDailyPerformanceValuesBetweenDates(
			$firstRecord->getCreatedAt(),
			$lastRecord->getCreatedAt(),
		);
		if (count($dailyValues) < 2) {
			return null;
		}

		return new PortfolioStatisticTotalValue(
			month: null,
			label: 'Portfolio performance since inception',
			investedAtStart: $this->parseCzkValue($investedAtStart),
			investedAtEnd: $this->parseCzkValue($investedAtEnd),
			valueAtStart: $this->parseCzkValue($valueAtStart),
			valueAtEnd: $this->parseCzkValue($valueAtEnd),
			startDate: $firstRecord->getCreatedAt(),
			endDate: $lastRecord->getCreatedAt(),
			cashFlowData: $dailyValues,
		);
	}

	private function parseCzkValue(PortfolioStatistic $statistic): float
	{
		return (float) str_replace(['CZK', ' '], '', $statistic->getValue());
	}

}
