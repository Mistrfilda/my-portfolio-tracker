<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Statistic\Performance\PortfolioPerformanceMonth;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\Performance\PortfolioPerformanceReconstructor;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PortfolioPerformanceRebuildFacadeTest extends TestCase
{

	public function testRebuildClearsCacheWhenThereIsNoHistory(): void
	{
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirst')->willReturn(null);
		$recordRepository->method('findLast')->willReturn(null);
		$monthRepository = $this->createMock(PortfolioPerformanceMonthRepository::class);
		$monthRepository->expects(self::once())->method('deleteAll');
		$reconstructor = $this->createMock(PortfolioPerformanceReconstructor::class);
		$reconstructor->expects(self::never())->method('reconstruct');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('beginTransaction');
		$entityManager->expects(self::never())->method('persist');
		$entityManager->expects(self::once())->method('flush');
		$entityManager->expects(self::once())->method('commit');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('info')
			->with('Portfolio performance cache rebuilt', ['months' => 0]);
		$totalValueProvider = $this->createMock(PortfolioStatisticTotalValueProvider::class);
		$totalValueProvider->expects(self::once())->method('invalidateCache');

		$facade = new PortfolioPerformanceRebuildFacade(
			$recordRepository,
			$monthRepository,
			$reconstructor,
			$entityManager,
			$this->createStub(DatetimeFactory::class),
			$logger,
			$totalValueProvider,
		);

		self::assertSame(0, $facade->rebuild());
	}

	public function testRebuildPersistsReconstructedMonthsAndCommits(): void
	{
		$firstRecord = new PortfolioStatisticRecord(new ImmutableDateTime('2026-01-01'));
		$lastRecord = new PortfolioStatisticRecord(new ImmutableDateTime('2026-02-28'));
		$now = new ImmutableDateTime('2026-03-01');
		$months = [
			$this->createMonth('2026-01-01', '2026-01-31'),
			$this->createMonth('2026-01-31', '2026-02-28'),
		];
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirst')->willReturn($firstRecord);
		$recordRepository->method('findLast')->willReturn($lastRecord);
		$monthRepository = $this->createMock(PortfolioPerformanceMonthRepository::class);
		$monthRepository->expects(self::once())->method('deleteAll');
		$reconstructor = $this->createMock(PortfolioPerformanceReconstructor::class);
		$reconstructor->expects(self::once())
			->method('reconstruct')
			->with($firstRecord->getCreatedAt(), $lastRecord->getCreatedAt(), 0.0, $now)
			->willReturn($months);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('beginTransaction');
		$persisted = [];
		$entityManager->expects(self::exactly(2))
			->method('persist')
			->willReturnCallback(static function (object $entity) use (&$persisted): void {
				$persisted[] = $entity;
			});
		$entityManager->expects(self::once())->method('flush');
		$steps = [];
		$entityManager->expects(self::once())->method(
			'commit',
		)->willReturnCallback(
			static function () use (&$steps): void {
				$steps[] = 'commit';
			},
		);
		$entityManager->expects(self::never())->method('rollback');
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('info')
			->with('Portfolio performance cache rebuilt', ['months' => 2]);
		$totalValueProvider = $this->createMock(PortfolioStatisticTotalValueProvider::class);
		$totalValueProvider->expects(self::once())
			->method('invalidateCache')
			->willReturnCallback(static function () use (&$steps): void {
				$steps[] = 'invalidate';
			});

		$facade = new PortfolioPerformanceRebuildFacade(
			$recordRepository,
			$monthRepository,
			$reconstructor,
			$entityManager,
			$datetimeFactory,
			$logger,
			$totalValueProvider,
		);

		self::assertSame(2, $facade->rebuild());
		self::assertSame($months, $persisted);
		self::assertSame(['commit', 'invalidate'], $steps);
	}

	public function testRebuildRollsBackWhenFlushFails(): void
	{
		$recordRepository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$recordRepository->method('findFirst')->willReturn(null);
		$recordRepository->method('findLast')->willReturn(null);
		$monthRepository = $this->createMock(PortfolioPerformanceMonthRepository::class);
		$monthRepository->expects(self::once())->method('deleteAll');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('beginTransaction');
		$entityManager->expects(self::once())
			->method('flush')
			->willThrowException(new RuntimeException('Flush failed'));
		$entityManager->expects(self::never())->method('commit');
		$entityManager->expects(self::once())->method('rollback');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::never())->method('info');
		$totalValueProvider = $this->createMock(PortfolioStatisticTotalValueProvider::class);
		$totalValueProvider->expects(self::never())->method('invalidateCache');

		$facade = new PortfolioPerformanceRebuildFacade(
			$recordRepository,
			$monthRepository,
			$this->createStub(PortfolioPerformanceReconstructor::class),
			$entityManager,
			$this->createStub(DatetimeFactory::class),
			$logger,
			$totalValueProvider,
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Flush failed');

		$facade->rebuild();
	}

	private function createMonth(string $start, string $end): PortfolioPerformanceMonth
	{
		$startDate = new ImmutableDateTime($start);
		$endDate = new ImmutableDateTime($end);

		return new PortfolioPerformanceMonth(
			new ImmutableDateTime($endDate->format('Y-m-01')),
			$startDate,
			$endDate,
			100.0,
			110.0,
			120.0,
			140.0,
			0.0,
			0.0,
			0.0,
			0.0,
			10.0,
			1.1,
			new ImmutableDateTime('2026-03-01'),
		);
	}

}
