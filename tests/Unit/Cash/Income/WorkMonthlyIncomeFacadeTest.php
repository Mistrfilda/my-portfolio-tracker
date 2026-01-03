<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Income;

use App\Cash\Income\WorkMonthlyIncome\HarvestTimeDownloader;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeFacade;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

class WorkMonthlyIncomeFacadeTest extends TestCase
{

	public function testDownloadCreatesNewRecord(): void
	{
		// Mock dependencies
		$workMonthlyIncomeRepositoryMock = Mockery::mock(WorkMonthlyIncomeRepository::class);
		$harvestTimeDownloaderMock = Mockery::mock(HarvestTimeDownloader::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// Create facade
		$currentHourlyRate = 500;
		$facade = new WorkMonthlyIncomeFacade(
			$currentHourlyRate,
			$workMonthlyIncomeRepositoryMock,
			$harvestTimeDownloaderMock,
			$datetimeFactoryMock,
			$entityManagerMock,
		);

		// Test data
		$timeEntries = [
			[
				'spent_date' => '2024-01-15',
				'hours' => 8.0,
			],
			[
				'spent_date' => '2024-01-16',
				'hours' => 7.5,
			],
		];

		$now = new ImmutableDateTime('now');

		// Setup expectations
		$harvestTimeDownloaderMock->shouldReceive('getData')
			->once()
			->andReturn($timeEntries);

		$workMonthlyIncomeRepositoryMock->shouldReceive('getByYearAndMonth')
			->once()
			->with(2024, 1)
			->andReturn(null);

		$datetimeFactoryMock->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock->shouldReceive('persist')
			->once()
			->with(Mockery::type(WorkMonthlyIncome::class));

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->download();

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

	public function testDownloadUpdatesExistingRecord(): void
	{
		// Mock dependencies
		$workMonthlyIncomeRepositoryMock = Mockery::mock(WorkMonthlyIncomeRepository::class);
		$harvestTimeDownloaderMock = Mockery::mock(HarvestTimeDownloader::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// Create facade
		$currentHourlyRate = 500;
		$facade = new WorkMonthlyIncomeFacade(
			$currentHourlyRate,
			$workMonthlyIncomeRepositoryMock,
			$harvestTimeDownloaderMock,
			$datetimeFactoryMock,
			$entityManagerMock,
		);

		// Test data
		$timeEntries = [
			[
				'spent_date' => '2024-02-10',
				'hours' => 10.0,
			],
		];

		$now = new ImmutableDateTime('now');
		$existingRecord = new WorkMonthlyIncome(2024, 2, 5.0, 450, new ImmutableDateTime('2024-02-01'));

		// Setup expectations
		$harvestTimeDownloaderMock->shouldReceive('getData')
			->once()
			->andReturn($timeEntries);

		$workMonthlyIncomeRepositoryMock->shouldReceive('getByYearAndMonth')
			->once()
			->with(2024, 2)
			->andReturn($existingRecord);

		$datetimeFactoryMock->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock->shouldReceive('persist')->never();
		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->download();

		// Validate that existing record was updated
		$this->assertEquals(10.0, $existingRecord->getHours());
	}

	public function testDownloadHandlesMultipleMonths(): void
	{
		// Mock dependencies
		$workMonthlyIncomeRepositoryMock = Mockery::mock(WorkMonthlyIncomeRepository::class);
		$harvestTimeDownloaderMock = Mockery::mock(HarvestTimeDownloader::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// Create facade
		$currentHourlyRate = 600;
		$facade = new WorkMonthlyIncomeFacade(
			$currentHourlyRate,
			$workMonthlyIncomeRepositoryMock,
			$harvestTimeDownloaderMock,
			$datetimeFactoryMock,
			$entityManagerMock,
		);

		// Test data - entries from multiple months
		$timeEntries = [
			[
				'spent_date' => '2024-01-15',
				'hours' => 8.0,
			],
			[
				'spent_date' => '2024-01-20',
				'hours' => 7.0,
			],
			[
				'spent_date' => '2024-02-05',
				'hours' => 9.0,
			],
		];

		$now = new ImmutableDateTime('now');

		// Setup expectations
		$harvestTimeDownloaderMock->shouldReceive('getData')
			->once()
			->andReturn($timeEntries);

		// January - no existing record
		$workMonthlyIncomeRepositoryMock->shouldReceive('getByYearAndMonth')
			->once()
			->with(2024, 1)
			->andReturn(null);

		// February - no existing record
		$workMonthlyIncomeRepositoryMock->shouldReceive('getByYearAndMonth')
			->once()
			->with(2024, 2)
			->andReturn(null);

		$datetimeFactoryMock->shouldReceive('createNow')
			->twice()
			->andReturn($now);

		$entityManagerMock->shouldReceive('persist')
			->twice()
			->with(Mockery::type(WorkMonthlyIncome::class));

		$entityManagerMock->shouldReceive('flush')->twice();

		// Execute
		$facade->download();

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

	public function testCreateBlank(): void
	{
		// Mock dependencies
		$workMonthlyIncomeRepositoryMock = Mockery::mock(WorkMonthlyIncomeRepository::class);
		$harvestTimeDownloaderMock = Mockery::mock(HarvestTimeDownloader::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// Create facade
		$currentHourlyRate = 550;
		$facade = new WorkMonthlyIncomeFacade(
			$currentHourlyRate,
			$workMonthlyIncomeRepositoryMock,
			$harvestTimeDownloaderMock,
			$datetimeFactoryMock,
			$entityManagerMock,
		);

		// Test data
		$year = 2024;
		$month = 3;
		$now = new ImmutableDateTime('now');

		// Setup expectations
		$datetimeFactoryMock->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock->shouldReceive('persist')
			->once()
			->with(Mockery::on(static fn ($income) => $income instanceof WorkMonthlyIncome
					&& $income->getYear() === $year
					&& $income->getMonth() === $month
					&& $income->getHours() === 0.0
					&& $income->getHourlyRate() === $currentHourlyRate));

		$entityManagerMock->shouldReceive('flush')->once();
		$entityManagerMock->shouldReceive('refresh')
			->once()
			->with(Mockery::type(WorkMonthlyIncome::class));

		// Execute
		$workMonthlyIncome = $facade->createBlank($year, $month);

		// Validate
		$this->assertInstanceOf(WorkMonthlyIncome::class, $workMonthlyIncome);
		$this->assertEquals($year, $workMonthlyIncome->getYear());
		$this->assertEquals($month, $workMonthlyIncome->getMonth());
		$this->assertEquals(0.0, $workMonthlyIncome->getHours());
		$this->assertEquals($currentHourlyRate, $workMonthlyIncome->getHourlyRate());
	}

	public function testDownloadAggregatesHoursForSameMonth(): void
	{
		// Mock dependencies
		$workMonthlyIncomeRepositoryMock = Mockery::mock(WorkMonthlyIncomeRepository::class);
		$harvestTimeDownloaderMock = Mockery::mock(HarvestTimeDownloader::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// Create facade
		$currentHourlyRate = 500;
		$facade = new WorkMonthlyIncomeFacade(
			$currentHourlyRate,
			$workMonthlyIncomeRepositoryMock,
			$harvestTimeDownloaderMock,
			$datetimeFactoryMock,
			$entityManagerMock,
		);

		// Test data - multiple entries in the same month
		$timeEntries = [
			[
				'spent_date' => '2024-01-10',
				'hours' => 8.0,
			],
			[
				'spent_date' => '2024-01-15',
				'hours' => 7.5,
			],
			[
				'spent_date' => '2024-01-20',
				'hours' => 9.0,
			],
		];

		$now = new ImmutableDateTime('now');

		// Setup expectations
		$harvestTimeDownloaderMock->shouldReceive('getData')
			->once()
			->andReturn($timeEntries);

		$workMonthlyIncomeRepositoryMock->shouldReceive('getByYearAndMonth')
			->once()
			->with(2024, 1)
			->andReturn(null);

		$datetimeFactoryMock->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$entityManagerMock->shouldReceive('persist')
			->once()
			->with(
				Mockery::on(
					static fn ($income) => $income instanceof WorkMonthlyIncome && $income->getHours() === 24.5,
				),
			);

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->download();

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

}
