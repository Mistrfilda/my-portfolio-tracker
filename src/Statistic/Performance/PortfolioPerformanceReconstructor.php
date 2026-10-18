<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Admin\AppAdmin;
use App\Statistic\PortfolioStatisticRecordRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioPerformanceReconstructor
{

	public function __construct(
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private readonly PortfolioPerformanceEventProvider $portfolioPerformanceEventProvider,
		private readonly PortfolioPerformanceCalculator $portfolioPerformanceCalculator,
	)
	{
	}

	/**
	 * @return array<PortfolioPerformanceMonth>
	 */
	public function reconstruct(
		AppAdmin $appAdmin,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		float $cashAtStart,
		ImmutableDateTime $now,
	): array
	{
		$dailyValues = $this->portfolioStatisticRecordRepository->findDailyPerformanceValuesBetweenDates(
			$start,
			$end,
			$appAdmin,
		);
		if (count($dailyValues) < 2) {
			return [];
		}

		$boundaries = $this->buildBoundaries($dailyValues);
		$months = [];

		for ($index = 1; $index < count($boundaries); $index++) {
			$startValue = $boundaries[$index - 1];
			$endValue = $boundaries[$index];
			$income = $this->portfolioPerformanceEventProvider->getIncomeBetween(
				$startValue['date'],
				$endValue['date'],
				$appAdmin,
			);

			if (
				$startValue['amount'] === 0.0
				&& $endValue['amount'] === 0.0
				&& $startValue['portfolioValue'] === 0.0
				&& $endValue['portfolioValue'] === 0.0
				&& $income->realizedProfit === 0.0
				&& $income->netDividends === 0.0
				&& $cashAtStart === 0.0
			) {
				continue;
			}

			$month = $this->portfolioPerformanceCalculator->calculateMonth(
				$appAdmin,
				$startValue['date'],
				$endValue['date'],
				$startValue['amount'],
				$endValue['amount'],
				$startValue['portfolioValue'],
				$endValue['portfolioValue'],
				$income->realizedProfit,
				$income->netDividends,
				$cashAtStart,
				$now,
			);
			$months[] = $month;
			$cashAtStart = $month->getCashAtEnd();
		}

		return $months;
	}

	/**
	 * @param array<array{date: ImmutableDateTime, amount: float, portfolioValue: float}> $dailyValues
	 * @return array<array{date: ImmutableDateTime, amount: float, portfolioValue: float}>
	 */
	private function buildBoundaries(array $dailyValues): array
	{
		$firstValue = $dailyValues[0];
		$lastValuesByMonth = [];
		foreach ($dailyValues as $dailyValue) {
			$lastValuesByMonth[$dailyValue['date']->format('Y-m')] = $dailyValue;
		}

		$boundaries = [$firstValue];
		foreach ($lastValuesByMonth as $lastValue) {
			if ($lastValue['date'] <= $boundaries[array_key_last($boundaries)]['date']) {
				continue;
			}

			$boundaries[] = $lastValue;
		}

		return $boundaries;
	}

}
