<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Statistic\Performance\PortfolioPerformanceCalculator;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Statistic\Performance\PortfolioPerformanceIncome;
use App\Statistic\Performance\PortfolioPerformanceReconstructor;
use App\Statistic\PortfolioStatisticRecordRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPerformanceReconstructorTest extends TestCase
{

	public function testReconstructReturnsEmptyResultWithoutTwoDailyValues(): void
	{
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findDailyPerformanceValuesBetweenDates')->willReturn([
			[
				'date' => new ImmutableDateTime('2026-01-01'),
				'amount' => 100.0,
				'portfolioValue' => 120.0,
			],
		]);
		$eventProvider = $this->createMock(PortfolioPerformanceEventProvider::class);
		$eventProvider->expects(self::never())->method('getIncomeBetween');
		$calculator = $this->createMock(PortfolioPerformanceCalculator::class);
		$calculator->expects(self::never())->method('calculateMonth');

		$reconstructor = new PortfolioPerformanceReconstructor(
			$recordRepository,
			$eventProvider,
			$calculator,
		);

		self::assertSame([], $reconstructor->reconstruct(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			0.0,
			new ImmutableDateTime('2026-02-01'),
		));
	}

	public function testReconstructUsesLastDailyValuePerMonthAndCarriesCashForward(): void
	{
		$start = new ImmutableDateTime('2026-01-02');
		$end = new ImmutableDateTime('2026-03-05');
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findDailyPerformanceValuesBetweenDates')->willReturn([
			['date' => $start, 'amount' => 100.0, 'portfolioValue' => 120.0],
			['date' => new ImmutableDateTime('2026-01-10'), 'amount' => 120.0, 'portfolioValue' => 140.0],
			['date' => new ImmutableDateTime('2026-01-31'), 'amount' => 150.0, 'portfolioValue' => 180.0],
			['date' => new ImmutableDateTime('2026-02-10'), 'amount' => 150.0, 'portfolioValue' => 190.0],
			['date' => new ImmutableDateTime('2026-02-28'), 'amount' => 150.0, 'portfolioValue' => 200.0],
			['date' => $end, 'amount' => 160.0, 'portfolioValue' => 220.0],
		]);

		$eventProvider = $this->createMock(PortfolioPerformanceEventProvider::class);
		$eventProvider->expects(self::exactly(3))
			->method('getIncomeBetween')
			->willReturnCallback(static fn (
				ImmutableDateTime $periodStart,
				ImmutableDateTime $periodEnd,
			): PortfolioPerformanceIncome => match ($periodEnd->format('Y-m-d')) {
					'2026-01-31' => new PortfolioPerformanceIncome(10.0, 5.0),
					'2026-02-28' => new PortfolioPerformanceIncome(20.0, 5.0),
					'2026-03-05' => new PortfolioPerformanceIncome(0.0, 0.0),
			});

		$reconstructor = new PortfolioPerformanceReconstructor(
			$recordRepository,
			$eventProvider,
			new PortfolioPerformanceCalculator(),
		);

		$months = $reconstructor->reconstruct(
			$start,
			$end,
			0.0,
			new ImmutableDateTime('2026-03-06'),
		);

		self::assertCount(3, $months);
		self::assertSame('2026-01-02', $months[0]->getPeriodStartAt()->format('Y-m-d'));
		self::assertSame('2026-01-31', $months[0]->getPeriodEndAt()->format('Y-m-d'));
		self::assertSame(35.0, $months[0]->getExternalContribution());
		self::assertSame(0.0, $months[0]->getCashAtEnd());
		self::assertSame('2026-02-28', $months[1]->getPeriodEndAt()->format('Y-m-d'));
		self::assertSame(25.0, $months[1]->getCashAtEnd());
		self::assertSame('2026-03-05', $months[2]->getPeriodEndAt()->format('Y-m-d'));
		self::assertSame(25.0, $months[2]->getCashAtStart());
		self::assertSame(15.0, $months[2]->getCashAtEnd());
	}

}
