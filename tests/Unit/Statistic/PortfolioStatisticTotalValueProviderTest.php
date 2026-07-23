<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Dashboard\DashboardValueGroupEnum;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\Performance\PortfolioPerformanceSummary;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioStatisticTotalValueProviderTest extends TestCase
{

	public function testBuildsAllTimeValueFromDailySnapshots(): void
	{
		$startRecord = $this->createRecord('2024-01-01', 100_000.0, 100_000.0);
		$endRecord = $this->createRecord('2024-01-03', 200_000.0, 220_000.0);
		$repository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$repository->method('findFirst')->willReturn($startRecord);
		$repository->method('findLast')->willReturn($endRecord);
		$performanceProvider = $this->createStub(PortfolioPerformanceProvider::class);
		$performanceProvider->method('getAllTimeSummary')->willReturn(new PortfolioPerformanceSummary(
			new ImmutableDateTime('2024-01-01'),
			new ImmutableDateTime('2024-01-03'),
			20.0,
			null,
			19.0,
			18.0,
		));

		$value = (new PortfolioStatisticTotalValueProvider($repository, $performanceProvider))->getAllTimeValue();

		self::assertNotNull($value);
		self::assertSame('2024-01-01', $value->getStartDate()?->format('Y-m-d'));
		self::assertSame('2024-01-03', $value->getEndDate()?->format('Y-m-d'));
		self::assertEqualsWithDelta(20.0, $value->getTimeWeightedReturn(), 0.0001);
	}

	public function testReturnsNullWithoutTwoSnapshots(): void
	{
		$record = $this->createRecord('2024-01-01', 100_000.0, 100_000.0);
		$repository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$repository->method('findFirst')->willReturn($record);
		$repository->method('findLast')->willReturn($record);
		$performanceProvider = $this->createStub(PortfolioPerformanceProvider::class);

		self::assertNull(
			(new PortfolioStatisticTotalValueProvider($repository, $performanceProvider))->getAllTimeValue(),
		);
	}

	private function createRecord(string $date, float $invested, float $portfolioValue): PortfolioStatisticRecord
	{
		$now = new ImmutableDateTime($date);
		$record = new PortfolioStatisticRecord($now);
		$investedStatistic = $this->createStatistic(
			$record,
			$now,
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
			$invested,
		);
		$valueStatistic = $this->createStatistic(
			$record,
			$now,
			PortolioStatisticType::TOTAL_VALUE_IN_CZK,
			$portfolioValue,
		);
		$record->getPortfolioStatistics()->add($investedStatistic);
		$record->getPortfolioStatistics()->add($valueStatistic);

		return $record;
	}

	private function createStatistic(
		PortfolioStatisticRecord $record,
		ImmutableDateTime $now,
		PortolioStatisticType $type,
		float $value,
	): PortfolioStatistic
	{
		return new PortfolioStatistic(
			$record,
			$now,
			DashboardValueGroupEnum::TOTAL_VALUES,
			$type->format(),
			sprintf('%.0f CZK', $value),
			'blue',
			null,
			null,
			$type,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			null,
		);
	}

}
