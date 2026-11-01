<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticCache;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;

class PortfolioStatisticTotalValueProvider
{

	private const string CACHE_KEY = 'all-time-value-v2-';

	public function __construct(
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private PortfolioPerformanceProvider $portfolioPerformanceProvider,
		private readonly PortfolioStatisticCache $cache,
		private CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function getAllTimeValue(AppAdmin|null $appAdmin = null): PortfolioStatisticTotalValue|null
	{
		$appAdmin ??= $this->currentAppAdminGetter->getAppAdmin();
		$value = $this->cache->load(
			$appAdmin,
			self::CACHE_KEY,
			fn (): PortfolioStatisticTotalValue|false => $this->buildAllTimeValue($appAdmin) ?? false,
		);
		assert($value === false || $value instanceof PortfolioStatisticTotalValue);

		return $value === false ? null : $value;
	}

	public function invalidateCache(AppAdmin $appAdmin): void
	{
		$this->cache->invalidate($appAdmin);
	}

	private function buildAllTimeValue(AppAdmin $appAdmin): PortfolioStatisticTotalValue|null
	{
		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst($appAdmin);
		$lastRecord = $this->portfolioStatisticRecordRepository->findLast($appAdmin);
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

		$performanceSummary = $this->portfolioPerformanceProvider->getAllTimeSummary($appAdmin);
		if ($performanceSummary === null) {
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
			performanceSummary: $performanceSummary,
		);
	}

	private function parseCzkValue(PortfolioStatistic $statistic): float
	{
		return (float) str_replace(['CZK', ' '], '', $statistic->getValue());
	}

}
