<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Statistic\PortfolioStatisticRecordRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceProvider
{

	public function __construct(
		private readonly PortfolioPerformanceMonthRepository $portfolioPerformanceMonthRepository,
		private readonly PortfolioPerformanceCalculator $portfolioPerformanceCalculator,
		private readonly PortfolioPerformanceReconstructor $portfolioPerformanceReconstructor,
		private readonly PortfolioPerformanceEventProvider $portfolioPerformanceEventProvider,
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function getAllTimeSummary(): PortfolioPerformanceSummary|null
	{
		return $this->portfolioPerformanceCalculator->calculateSummary(
			$this->portfolioPerformanceMonthRepository->findAllOrdered(),
		);
	}

	public function getSummaryBetween(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioPerformanceSummary|null
	{
		$cashAtStart = $this->resolveCashAtStart($start);
		$months = $this->portfolioPerformanceReconstructor->reconstruct(
			$start,
			$end,
			$cashAtStart,
			$this->datetimeFactory->createNow(),
		);

		return $this->portfolioPerformanceCalculator->calculateSummary($months);
	}

	public function getIncomeBetween(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioPerformanceIncome
	{
		return $this->portfolioPerformanceEventProvider->getIncomeBetween($start, $end);
	}

	private function resolveCashAtStart(ImmutableDateTime $start): float
	{
		$previousMonth = $this->portfolioPerformanceMonthRepository->findLastEndingAtOrBefore($start);
		if ($previousMonth !== null) {
			if ($previousMonth->getPeriodEndAt()->getTimestamp() === $start->getTimestamp()) {
				return $previousMonth->getCashAtEnd();
			}

			$bridge = $this->portfolioPerformanceReconstructor->reconstruct(
				$previousMonth->getPeriodEndAt(),
				$start,
				$previousMonth->getCashAtEnd(),
				$this->datetimeFactory->createNow(),
			);

			if ($bridge !== []) {
				return $bridge[count($bridge) - 1]->getCashAtEnd();
			}

			return $previousMonth->getCashAtEnd();
		}

		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst();
		if ($firstRecord === null || $firstRecord->getCreatedAt() >= $start) {
			return 0.0;
		}

		$bridge = $this->portfolioPerformanceReconstructor->reconstruct(
			$firstRecord->getCreatedAt(),
			$start,
			0.0,
			$this->datetimeFactory->createNow(),
		);

		return $bridge !== [] ? $bridge[count($bridge) - 1]->getCashAtEnd() : 0.0;
	}

}
