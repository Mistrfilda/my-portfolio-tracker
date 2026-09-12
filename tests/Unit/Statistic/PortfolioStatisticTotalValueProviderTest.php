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
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;

class PortfolioStatisticTotalValueProviderTest extends TestCase
{

	public function testBuildsAllTimeValueFromDailySnapshots(): void
	{
		$startRecord = $this->createRecord('2024-01-01', 100_000.0, 100_000.0);
		$endRecord = $this->createRecord('2024-01-03', 200_000.0, 220_000.0);
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::once())->method('findFirst')->willReturn($startRecord);
		$repository->expects(self::once())->method('findLast')->willReturn($endRecord);
		$performanceProvider = $this->createMock(PortfolioPerformanceProvider::class);
		$performanceProvider->expects(self::once())->method(
			'getAllTimeSummary',
		)->willReturn(
			new PortfolioPerformanceSummary(
				new ImmutableDateTime('2024-01-01'),
				new ImmutableDateTime('2024-01-03'),
				20.0,
				15.0,
				19.0,
				18.0,
			),
		);

		$directory = sys_get_temp_dir() . '/portfolio-performance-cache-' . bin2hex(random_bytes(8));
		FileSystem::createDir($directory);
		try {
			$value = (new PortfolioStatisticTotalValueProvider(
				$repository,
				$performanceProvider,
				new FileStorage($directory),
			))
				->getAllTimeValue();
			$cachedValue = (new PortfolioStatisticTotalValueProvider(
				$repository,
				$performanceProvider,
				new FileStorage($directory),
			))
				->getAllTimeValue();
		} finally {
			FileSystem::delete($directory);
		}

		self::assertNotNull($value);
		self::assertEquals($value, $cachedValue);
		self::assertSame('2024-01-01', $value->getStartDate()?->format('Y-m-d'));
		self::assertSame('2024-01-03', $value->getEndDate()?->format('Y-m-d'));
		self::assertEqualsWithDelta(20.0, $value->getTimeWeightedReturn(), 0.0001);
		self::assertSame(15.0, $value->getAnnualizedTwr());
		self::assertSame(19.0, $value->getMoneyWeightedReturn());
		self::assertSame(18.0, $value->getXirr());
	}

	public function testReturnsNullWithoutTwoSnapshots(): void
	{
		$record = $this->createRecord('2024-01-01', 100_000.0, 100_000.0);
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::once())->method('findFirst')->willReturn($record);
		$repository->expects(self::once())->method('findLast')->willReturn($record);
		$performanceProvider = $this->createMock(PortfolioPerformanceProvider::class);
		$performanceProvider->expects(self::never())->method('getAllTimeSummary');
		$storage = new MemoryStorage();

		self::assertNull(
			(new PortfolioStatisticTotalValueProvider($repository, $performanceProvider, $storage))->getAllTimeValue(),
		);
		self::assertNull(
			(new PortfolioStatisticTotalValueProvider($repository, $performanceProvider, $storage))->getAllTimeValue(),
		);
	}

	public function testInvalidationMakesNewHistoryAvailableAcrossProviderInstances(): void
	{
		$startRecord = $this->createRecord('2024-01-01', 100_000.0, 100_000.0);
		$endRecord = $this->createRecord('2024-01-03', 200_000.0, 220_000.0);
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::exactly(2))->method('findFirst')->willReturn($startRecord);
		$repository->expects(self::exactly(2))->method('findLast')->willReturn($startRecord, $endRecord);
		$performanceProvider = $this->createMock(PortfolioPerformanceProvider::class);
		$performanceProvider->expects(self::once())->method(
			'getAllTimeSummary',
		)->willReturn(
			new PortfolioPerformanceSummary(
				new ImmutableDateTime('2024-01-01'),
				new ImmutableDateTime('2024-01-03'),
				20.0,
				null,
				19.0,
				18.0,
			),
		);
		$storage = new MemoryStorage();
		$provider = new PortfolioStatisticTotalValueProvider($repository, $performanceProvider, $storage);
		$otherProvider = new PortfolioStatisticTotalValueProvider($repository, $performanceProvider, $storage);

		self::assertNull($provider->getAllTimeValue());
		self::assertNull($otherProvider->getAllTimeValue());

		$otherProvider->invalidateCache();
		$value = $provider->getAllTimeValue();

		self::assertNotNull($value);
		self::assertSame('2024-01-03', $value->getEndDate()?->format('Y-m-d'));
		self::assertSame(20.0, $value->getTimeWeightedReturn());
		self::assertSame($value, $otherProvider->getAllTimeValue());
	}

	public function testCachesMissingHistoryWithOneHourExpiration(): void
	{
		$repository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$repository->method('findFirst')->willReturn(null);
		$repository->method('findLast')->willReturn(null);
		$storage = $this->createMock(Storage::class);
		$storage->expects(self::once())->method('read')->willReturn(null);
		$storage->expects(self::once())->method('write')->with(
			self::anything(),
			false,
			[Cache::Expire => 3600],
		);

		$provider = new PortfolioStatisticTotalValueProvider(
			$repository,
			$this->createStub(PortfolioPerformanceProvider::class),
			$storage,
		);

		self::assertNull($provider->getAllTimeValue());
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
