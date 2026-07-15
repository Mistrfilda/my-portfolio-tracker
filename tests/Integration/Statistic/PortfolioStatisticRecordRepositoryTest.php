<?php

declare(strict_types = 1);

namespace App\Test\Integration\Statistic;

use App\Dashboard\DashboardValueGroupEnum;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioStatisticRecordRepositoryTest extends IntegrationTestCase
{

	private PortfolioStatisticRecordRepository $repository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = $this->getService(PortfolioStatisticRecordRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testFindsPeriodBoundaryRecordsAndDailyInvestedValues(): void
	{
		$this->persistRecord('2077-03-01 08:00:00', '1 000 CZK');
		$this->persistRecord('2077-03-10 12:00:00', '1 250 CZK');
		$this->persistRecord('2077-03-20 20:00:00', '1 500 CZK');
		$this->entityManager->flush();
		$this->entityManager->clear();

		$start = new ImmutableDateTime('2077-03-01 00:00:00');
		$end = new ImmutableDateTime('2077-03-31 23:59:59');

		self::assertSame(
			'2077-03-01 08:00:00',
			$this->repository->findFirstBetweenDates($start, $end)?->getCreatedAt()->format('Y-m-d H:i:s'),
		);
		self::assertSame(
			'2077-03-20 20:00:00',
			$this->repository->findLastBetweenDates($start, $end)?->getCreatedAt()->format('Y-m-d H:i:s'),
		);
		self::assertSame(
			['2077-03-01', '2077-03-10', '2077-03-20'],
			array_map(
				static fn (PortfolioStatisticRecord $record): string => $record->getCreatedAt()->format('Y-m-d'),
				$this->repository->findBetweenDates($start, $end),
			),
		);

		$dailyInvestedValues = $this->repository->findDailyInvestedCzkBetweenDates($start, $end);
		self::assertSame([1_000.0, 1_250.0, 1_500.0], array_column($dailyInvestedValues, 'amount'));
		self::assertSame(
			['2077-03-01', '2077-03-10', '2077-03-20'],
			array_map(
				static fn (array $value): string => $value['date']->format('Y-m-d'),
				$dailyInvestedValues,
			),
		);
	}

	public function testReturnsNoRecordsOutsideRequestedPeriod(): void
	{
		$start = new ImmutableDateTime('2099-01-01 00:00:00');
		$end = new ImmutableDateTime('2099-01-31 23:59:59');

		self::assertNull($this->repository->findFirstBetweenDates($start, $end));
		self::assertNull($this->repository->findLastBetweenDates($start, $end));
		self::assertSame([], $this->repository->findBetweenDates($start, $end));
		self::assertSame([], $this->repository->findDailyInvestedCzkBetweenDates($start, $end));
	}

	private function persistRecord(string $date, string $investedValue): void
	{
		$now = new ImmutableDateTime($date);
		$record = new PortfolioStatisticRecord($now);
		$statistic = new PortfolioStatistic(
			$record,
			$now,
			DashboardValueGroupEnum::TOTAL_VALUES,
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK->format(),
			$investedValue,
			'blue',
			null,
			null,
			PortolioStatisticType::TOTAL_INVESTED_IN_CZK,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			null,
		);
		$record->getPortfolioStatistics()->add($statistic);

		$this->entityManager->persist($record);
		$this->entityManager->persist($statistic);
	}

}
