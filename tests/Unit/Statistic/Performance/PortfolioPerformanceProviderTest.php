<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Statistic\Performance\PortfolioPerformanceCalculator;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Statistic\Performance\PortfolioPerformanceIncome;
use App\Statistic\Performance\PortfolioPerformanceMonth;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\Performance\PortfolioPerformanceReconstructor;
use App\Statistic\Performance\PortfolioPerformanceSummary;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPerformanceProviderTest extends TestCase
{

	public function testGetAllTimeSummaryUsesCachedMonths(): void
	{
		$month = $this->createMonth('2026-01-01', '2026-01-31', 0.0);
		$summary = $this->createSummary();
		$monthRepository = $this->createMock(PortfolioPerformanceMonthRepository::class);
		$monthRepository->expects(self::once())->method('findAllOrdered')->willReturn([$month]);
		$calculator = $this->createMock(PortfolioPerformanceCalculator::class);
		$calculator->expects(self::once())->method('calculateSummary')->with([$month])->willReturn($summary);

		$provider = $this->createProvider($monthRepository, calculator: $calculator);

		self::assertSame($summary, $provider->getAllTimeSummary());
	}

	public function testGetSummaryUsesCashFromCacheEndingExactlyAtStart(): void
	{
		$start = new ImmutableDateTime('2026-02-01 00:00:00');
		$end = new ImmutableDateTime('2026-02-28 23:59:59');
		$now = new ImmutableDateTime('2026-03-01 12:00:00');
		$cachedMonth = $this->createMonth('2026-01-01', $start->format('Y-m-d H:i:s'), 75.0);
		$requestedMonth = $this->createMonth($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), 0.0);
		$summary = $this->createSummary();

		$monthRepository = $this->createMock(PortfolioPerformanceMonthRepository::class);
		$monthRepository->expects(self::once())
			->method('findLastEndingAtOrBefore')
			->with($start)
			->willReturn($cachedMonth);
		$reconstructor = $this->createMock(PortfolioPerformanceReconstructor::class);
		$reconstructor->expects(self::once())
			->method('reconstruct')
			->with($start, $end, 75.0, $now)
			->willReturn([$requestedMonth]);
		$calculator = $this->createMock(PortfolioPerformanceCalculator::class);
		$calculator->expects(self::once())
			->method('calculateSummary')
			->with([$requestedMonth])
			->willReturn($summary);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$provider = $this->createProvider(
			$monthRepository,
			$calculator,
			$reconstructor,
			datetimeFactory: $datetimeFactory,
		);

		self::assertSame($summary, $provider->getSummaryBetween($start, $end));
	}

	public function testGetSummaryBridgesGapFromPreviousCachedMonth(): void
	{
		$previousEnd = new ImmutableDateTime('2026-01-31 23:59:59');
		$start = new ImmutableDateTime('2026-02-10 00:00:00');
		$end = new ImmutableDateTime('2026-02-28 23:59:59');
		$now = new ImmutableDateTime('2026-03-01 12:00:00');
		$cachedMonth = $this->createMonth('2026-01-01', $previousEnd->format('Y-m-d H:i:s'), 50.0);
		$bridgeMonth = $this->createMonth($previousEnd->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s'), 80.0);
		$requestedMonth = $this->createMonth($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), 0.0);
		$summary = $this->createSummary();

		$monthRepository = $this->createStub(PortfolioPerformanceMonthRepository::class);
		$monthRepository->method('findLastEndingAtOrBefore')->willReturn($cachedMonth);
		$reconstructor = $this->createMock(PortfolioPerformanceReconstructor::class);
		$call = 0;
		$reconstructor->expects(self::exactly(2))
			->method('reconstruct')
			->willReturnCallback(static function (
				ImmutableDateTime $actualStart,
				ImmutableDateTime $actualEnd,
				float $cashAtStart,
				ImmutableDateTime $actualNow,
			) use (
				&$call,
				$previousEnd,
				$start,
				$end,
				$now,
				$bridgeMonth,
				$requestedMonth,
			): array {
				$call++;
				self::assertSame($now, $actualNow);
				if ($call === 1) {
					self::assertSame(
						$previousEnd->format('Y-m-d H:i:s'),
						$actualStart->format('Y-m-d H:i:s'),
					);
					self::assertSame($start, $actualEnd);
					self::assertSame(50.0, $cashAtStart);
					return [$bridgeMonth];
				}

				self::assertSame($start, $actualStart);
				self::assertSame($end, $actualEnd);
				self::assertSame(80.0, $cashAtStart);
				return [$requestedMonth];
			});
		$calculator = $this->createStub(PortfolioPerformanceCalculator::class);
		$calculator->method('calculateSummary')->willReturn($summary);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$provider = $this->createProvider(
			$monthRepository,
			$calculator,
			$reconstructor,
			datetimeFactory: $datetimeFactory,
		);

		self::assertSame($summary, $provider->getSummaryBetween($start, $end));
	}

	public function testGetSummaryUsesZeroCashWithoutEarlierHistory(): void
	{
		$start = new ImmutableDateTime('2026-02-01');
		$end = new ImmutableDateTime('2026-02-28');
		$now = new ImmutableDateTime('2026-03-01');
		$monthRepository = $this->createStub(PortfolioPerformanceMonthRepository::class);
		$monthRepository->method('findLastEndingAtOrBefore')->willReturn(null);
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirst')->willReturn(new PortfolioStatisticRecord($start));
		$reconstructor = $this->createMock(PortfolioPerformanceReconstructor::class);
		$reconstructor->expects(self::once())
			->method('reconstruct')
			->with($start, $end, 0.0, $now)
			->willReturn([]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$provider = $this->createProvider(
			$monthRepository,
			reconstructor: $reconstructor,
			recordRepository: $recordRepository,
			datetimeFactory: $datetimeFactory,
		);

		self::assertNull($provider->getSummaryBetween($start, $end));
	}

	public function testGetIncomeDelegatesToEventProvider(): void
	{
		$start = new ImmutableDateTime('2026-01-01');
		$end = new ImmutableDateTime('2026-01-31');
		$income = new PortfolioPerformanceIncome(20.0, 5.0);
		$eventProvider = $this->createMock(PortfolioPerformanceEventProvider::class);
		$eventProvider->expects(self::once())
			->method('getIncomeBetween')
			->with($start, $end)
			->willReturn($income);

		$provider = $this->createProvider(eventProvider: $eventProvider);

		self::assertSame($income, $provider->getIncomeBetween($start, $end));
	}

	private function createProvider(
		PortfolioPerformanceMonthRepository|null $monthRepository = null,
		PortfolioPerformanceCalculator|null $calculator = null,
		PortfolioPerformanceReconstructor|null $reconstructor = null,
		PortfolioPerformanceEventProvider|null $eventProvider = null,
		PortfolioStatisticRecordRepository|null $recordRepository = null,
		DatetimeFactory|null $datetimeFactory = null,
	): PortfolioPerformanceProvider
	{
		return new PortfolioPerformanceProvider(
			$monthRepository ?? $this->createStub(PortfolioPerformanceMonthRepository::class),
			$calculator ?? new PortfolioPerformanceCalculator(),
			$reconstructor ?? $this->createStub(PortfolioPerformanceReconstructor::class),
			$eventProvider ?? $this->createStub(PortfolioPerformanceEventProvider::class),
			$recordRepository ?? $this->createStub(PortfolioStatisticRecordRepository::class),
			$datetimeFactory ?? $this->createStub(DatetimeFactory::class),
		);
	}

	private function createMonth(string $start, string $end, float $cashAtEnd): PortfolioPerformanceMonth
	{
		$startDate = new ImmutableDateTime($start);
		$endDate = new ImmutableDateTime($end);

		return new PortfolioPerformanceMonth(
			new ImmutableDateTime($endDate->format('Y-m-01')),
			$startDate,
			$endDate,
			100.0,
			100.0,
			120.0,
			130.0,
			0.0,
			0.0,
			0.0,
			$cashAtEnd,
			0.0,
			1.1,
			new ImmutableDateTime('2026-03-01'),
		);
	}

	private function createSummary(): PortfolioPerformanceSummary
	{
		return new PortfolioPerformanceSummary(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			10.0,
			null,
			10.0,
			10.0,
		);
	}

}
