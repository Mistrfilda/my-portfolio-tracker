<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioReportPeriodResolver
{

	public function resolve(
		PortfolioReportPeriodTypeEnum $type,
		ImmutableDateTime $referenceDate,
	): PortfolioReportPeriod
	{
		$normalizedReferenceDate = $referenceDate->setTime(12, 0, 0);

		return match ($type) {
			PortfolioReportPeriodTypeEnum::DAILY => new PortfolioReportPeriod(
				$type,
				$normalizedReferenceDate->setTime(0, 0, 1),
				$normalizedReferenceDate->setTime(23, 59, 59),
				$normalizedReferenceDate,
			),
			PortfolioReportPeriodTypeEnum::WEEKLY => $this->resolveWeekly($type, $normalizedReferenceDate),
			PortfolioReportPeriodTypeEnum::MONTHLY => $this->resolveMonthly($type, $normalizedReferenceDate),
			PortfolioReportPeriodTypeEnum::BIMONTHLY => $this->resolveBimonthly($type, $normalizedReferenceDate),
		};
	}

	private function resolveWeekly(
		PortfolioReportPeriodTypeEnum $type,
		ImmutableDateTime $referenceDate,
	): PortfolioReportPeriod
	{
		$dayOfWeek = (int) $referenceDate->format('N');
		$dateFrom = $referenceDate->modify(sprintf('-%d days', $dayOfWeek - 1))->setTime(0, 0, 1);
		$dateTo = $dateFrom->modify('+6 days')->setTime(23, 59, 59);

		return new PortfolioReportPeriod($type, $dateFrom, $dateTo, $referenceDate);
	}

	private function resolveMonthly(
		PortfolioReportPeriodTypeEnum $type,
		ImmutableDateTime $referenceDate,
	): PortfolioReportPeriod
	{
		$dateFrom = $referenceDate->modify('first day of this month')->setTime(0, 0, 1);
		$dateTo = $referenceDate->modify('last day of this month')->setTime(23, 59, 59);

		return new PortfolioReportPeriod($type, $dateFrom, $dateTo, $referenceDate);
	}

	private function resolveBimonthly(
		PortfolioReportPeriodTypeEnum $type,
		ImmutableDateTime $referenceDate,
	): PortfolioReportPeriod
	{
		$month = (int) $referenceDate->format('n');
		$startMonthOffset = $month % 2 === 0 ? -1 : 0;
		$dateFrom = $referenceDate->modify(sprintf('%d months', $startMonthOffset))
			->modify('first day of this month')
			->setTime(0, 0, 1);
		$dateTo = $dateFrom->modify('+1 month')->modify('last day of this month')->setTime(23, 59, 59);

		return new PortfolioReportPeriod($type, $dateFrom, $dateTo, $referenceDate);
	}

}
