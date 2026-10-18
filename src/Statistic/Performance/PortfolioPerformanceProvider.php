<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
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
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function getAllTimeSummary(AppAdmin|null $appAdmin = null): PortfolioPerformanceSummary|null
	{
		return $this->portfolioPerformanceCalculator->calculateSummary(
			$this->portfolioPerformanceMonthRepository->findAllOrdered($appAdmin),
		);
	}

	public function getSummaryBetween(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): PortfolioPerformanceSummary|null
	{
		$appAdmin ??= $this->currentAppAdminGetter->getAppAdmin();
		$cashAtStart = $this->resolveCashAtStart($start, $appAdmin);
		$months = $this->portfolioPerformanceReconstructor->reconstruct(
			$appAdmin,
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
		AppAdmin|null $appAdmin = null,
	): PortfolioPerformanceIncome
	{
		return $this->portfolioPerformanceEventProvider->getIncomeBetween($start, $end, $appAdmin);
	}

	public function invalidateCache(AppAdmin $appAdmin): void
	{
		$this->portfolioPerformanceEventProvider->invalidateCache($appAdmin);
	}

	private function resolveCashAtStart(ImmutableDateTime $start, AppAdmin $appAdmin): float
	{
		$previousMonth = $this->portfolioPerformanceMonthRepository->findLastEndingAtOrBefore($start, $appAdmin);
		if ($previousMonth !== null) {
			if ($previousMonth->getPeriodEndAt()->getTimestamp() === $start->getTimestamp()) {
				return $previousMonth->getCashAtEnd();
			}

			$bridge = $this->portfolioPerformanceReconstructor->reconstruct(
				$appAdmin,
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

		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst($appAdmin);
		if ($firstRecord === null || $firstRecord->getCreatedAt() >= $start) {
			return 0.0;
		}

		$bridge = $this->portfolioPerformanceReconstructor->reconstruct(
			$appAdmin,
			$firstRecord->getCreatedAt(),
			$start,
			0.0,
			$this->datetimeFactory->createNow(),
		);

		return $bridge !== [] ? $bridge[count($bridge) - 1]->getCashAtEnd() : 0.0;
	}

}
