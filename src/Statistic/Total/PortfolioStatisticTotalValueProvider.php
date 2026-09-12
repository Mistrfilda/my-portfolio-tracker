<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

class PortfolioStatisticTotalValueProvider
{

	private const string CACHE_KEY = 'all-time-value-v1';

	private readonly Cache $cache;

	public function __construct(
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private PortfolioPerformanceProvider $portfolioPerformanceProvider,
		Storage $storage,
	)
	{
		$this->cache = new Cache($storage, self::class);
	}

	public function getAllTimeValue(): PortfolioStatisticTotalValue|null
	{
		$value = $this->cache->load(
			self::CACHE_KEY,
			fn (): PortfolioStatisticTotalValue|false => $this->buildAllTimeValue() ?? false,
			[Cache::Expire => '1 hour'],
		);
		assert($value === false || $value instanceof PortfolioStatisticTotalValue);

		return $value === false ? null : $value;
	}

	public function invalidateCache(): void
	{
		$this->cache->remove(self::CACHE_KEY);
	}

	private function buildAllTimeValue(): PortfolioStatisticTotalValue|null
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

		$performanceSummary = $this->portfolioPerformanceProvider->getAllTimeSummary();
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
