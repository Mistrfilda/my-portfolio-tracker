<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Dashboard\DashboardValueGroupEnum;
use App\Statistic\Performance\PortfolioPerformanceIncome;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticCache;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Statistic\Total\PortfolioTotalStatisticControlFacade;
use Doctrine\DBAL\Connection;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Caching\Storage;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class PortfolioTotalStatisticControlFacadeTest extends TestCase
{

	private AppAdmin $owner;

	protected function setUp(): void
	{
		parent::setUp();
		$this->owner = $this->createStub(AppAdmin::class);
		$this->owner->method('getId')->willReturn(Uuid::uuid4());
	}

	public function testCachesYearGroupsAcrossInstancesWithoutCalculatingHiddenMonths(): void
	{
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::once())->method('findMinMaxDateByMonth')
			->with(2026, $this->owner)->willReturn($this->records());
		$performance = $this->performance(1);
		$directory = sys_get_temp_dir() . '/portfolio-groups-cache-' . bin2hex(random_bytes(8));
		FileSystem::createDir($directory);
		try {
			$first = $this->facade($repository, $performance, new FileStorage($directory))->getData(onlyYears: true);
			$second = $this->facade($repository, $performance, new FileStorage($directory))->getData(onlyYears: true);
		} finally {
			FileSystem::delete($directory);
		}

		self::assertEquals($first, $second);
		self::assertCount(1, $second);
		self::assertSame([], $second[0]->getValues());
		self::assertSame(1600.0, $second[0]->getYearValue()->getValueAtEnd());
	}

	public function testFullHistoryAndDashboardHaveSeparateCachedResults(): void
	{
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::exactly(2))->method('findMinMaxDateByMonth')->willReturn($this->records());
		$facade = $this->facade($repository, $this->performance(4), new MemoryStorage());
		$years = $facade->getData(onlyYears: true);
		$months = $facade->getData();
		self::assertEquals($years, $facade->getData(onlyYears: true));
		self::assertEquals($months, $facade->getData());
		self::assertSame([], $years[0]->getValues());
		self::assertCount(2, $months[0]->getValues());
		self::assertEquals($months[0]->getYearValue(), $years[0]->getYearValue());
	}

	public function testOwnerInvalidationRebuildsGroups(): void
	{
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::exactly(2))->method('findMinMaxDateByMonth')
			->willReturn($this->records(), []);
		$storage = new MemoryStorage();
		$facade = $this->facade($repository, $this->performance(1), $storage);
		self::assertCount(1, $facade->getData(onlyYears: true));
		(new PortfolioStatisticCache($storage, $this->createStub(Connection::class)))->invalidate($this->owner);
		self::assertSame([], $facade->getData(onlyYears: true));
	}

	public function testNewYearDoesNotReuseThePreviousYearsCache(): void
	{
		$repository = $this->createMock(PortfolioStatisticRecordRepository::class);
		$repository->expects(self::exactly(3))->method('findMinMaxDateByMonth')->willReturn([]);
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(
			new ImmutableDateTime('2026-12-31'),
			new ImmutableDateTime('2027-01-01'),
		);
		$facade = $this->facade($repository, $this->performance(0), new MemoryStorage(), $clock);
		self::assertSame([], $facade->getData(onlyYears: true));
		self::assertSame([], $facade->getData(onlyYears: true));
	}

	private function facade(
		PortfolioStatisticRecordRepository $repository,
		PortfolioPerformanceProvider $performance,
		Storage $storage,
		DatetimeFactory|null $clock = null,
	): PortfolioTotalStatisticControlFacade
	{
		if ($clock === null) {
			$clock = $this->createStub(DatetimeFactory::class);
			$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-03-01'));
		}

		$current = $this->createStub(CurrentAppAdminGetter::class);
		$current->method('getAppAdmin')->willReturn($this->owner);
		return new PortfolioTotalStatisticControlFacade(
			2026,
			$clock,
			$repository,
			$performance,
			$current,
			new PortfolioStatisticCache($storage, $this->createStub(Connection::class)),
		);
	}

	private function performance(int $summaries): PortfolioPerformanceProvider
	{
		$performance = $this->createMock(PortfolioPerformanceProvider::class);
		$performance->expects(self::exactly($summaries))->method('getSummaryBetween')
			->with(self::anything(), self::anything(), $this->owner)->willReturn(null);
		$performance->expects(self::exactly($summaries))->method('getIncomeBetween')
			->willReturn(new PortfolioPerformanceIncome(0.0, 0.0));
		return $performance;
	}

	/** @return array<PortfolioStatisticRecord> */
	private function records(): array
	{
		$records = [];
		foreach (['2026-01-01', '2026-01-31', '2026-02-01', '2026-02-28'] as $date) {
			$at = new ImmutableDateTime($date);
			$record = new PortfolioStatisticRecord($this->owner, $at);
			foreach ([
				PortolioStatisticType::TOTAL_INVESTED_IN_CZK->value => '1000 CZK',
				PortolioStatisticType::TOTAL_VALUE_IN_CZK->value => '1600 CZK',
			] as $type => $value) {
				$record->addPortfolioStatistic(new PortfolioStatistic(
					$record,
					$at,
					DashboardValueGroupEnum::TOTAL_VALUES,
					$type,
					$value,
					'blue',
					null,
					null,
					PortolioStatisticType::from($type),
					PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
					null,
				));
			}

			$records[] = $record;
		}

		return $records;
	}

}
