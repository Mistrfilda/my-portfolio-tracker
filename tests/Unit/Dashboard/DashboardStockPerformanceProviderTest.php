<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard;

use App\Admin\AppAdmin;
use App\Dashboard\DashboardStockPerformanceProvider;
use App\Statistic\PortfolioStatisticCache;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use Doctrine\DBAL\Connection;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Nette\Caching\Storages\FileStorage;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DashboardStockPerformanceProviderTest extends TestCase
{

	public function testWorkerCachesScalarResultsForWebWithoutLoadingPriceHistoryAgain(): void
	{
		$owner = $this->owner();
		$asset = $this->createMock(StockAsset::class);
		$id = Uuid::uuid4();
		$asset->method('getId')->willReturn($id);
		$asset->method('getName')->willReturn('Example stock');
		$asset->method('getTicker')->willReturn('EXAMPLE');
		$asset->method('shouldBeUpdated')->willReturn(true);
		$asset->expects(self::once())->method('getTrend')->willReturn(7.25);
		$stocks = $this->createMock(StockAssetRepository::class);
		$stocks->expects(self::once())->method('findWithPositionsForAppAdmin')->with($owner, true)
			->willReturn([$asset]);
		$clock = $this->clock();
		$directory = sys_get_temp_dir() . '/stock-performance-cache-' . bin2hex(random_bytes(8));
		FileSystem::createDir($directory);
		try {
			$worker = new DashboardStockPerformanceProvider(
				$stocks,
				$clock,
				new PortfolioStatisticCache(new FileStorage($directory), $this->createStub(Connection::class)),
			);
			$first = $worker->getDailyPerformance($owner);
			$webStocks = $this->createMock(StockAssetRepository::class);
			$webStocks->expects(self::never())->method('findWithPositionsForAppAdmin');
			$web = new DashboardStockPerformanceProvider(
				$webStocks,
				$clock,
				new PortfolioStatisticCache(new FileStorage($directory), $this->createStub(Connection::class)),
			);
			self::assertSame($first, $web->getDailyPerformance($owner));
			self::assertSame([
				['id' => $id->toString(), 'name' => 'Example stock', 'ticker' => 'EXAMPLE', 'trend' => 7.25],
			], $first);
		} finally {
			FileSystem::delete($directory);
		}
	}

	public function testNewDayAndPriceImportRefreshTheCachedRanking(): void
	{
		$owner = $this->owner();
		$asset = $this->createMock(StockAsset::class);
		$asset->method('getId')->willReturn(Uuid::uuid4());
		$asset->method('getName')->willReturn('Example stock');
		$asset->method('getTicker')->willReturn('EXAMPLE');
		$asset->method('shouldBeUpdated')->willReturn(true);
		$asset->expects(self::exactly(3))->method('getTrend')->willReturn(7.25, 4.5, -2.0);
		$stocks = $this->createMock(StockAssetRepository::class);
		$stocks->expects(self::exactly(3))->method('findWithPositionsForAppAdmin')->with($owner, true)
			->willReturn([$asset]);
		$clock = $this->createStub(DatetimeFactory::class);
		$today = new ImmutableDateTime('2026-11-01');
		$tomorrow = new ImmutableDateTime('2026-11-02');
		$clock->method('createNow')->willReturn($today, $today, $tomorrow, $tomorrow);
		$cache = new PortfolioStatisticCache(new MemoryStorage(), $this->createStub(Connection::class));
		$provider = new DashboardStockPerformanceProvider($stocks, $clock, $cache);
		self::assertSame(7.25, $provider->getDailyPerformance($owner)[0]['trend']);
		self::assertSame(7.25, $provider->getDailyPerformance($owner)[0]['trend']);
		self::assertSame(4.5, $provider->getDailyPerformance($owner)[0]['trend']);
		$cache->invalidateAll();
		self::assertSame(-2.0, $provider->getDailyPerformance($owner)[0]['trend']);
	}

	private function clock(): DatetimeFactory
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-11-01'));
		return $clock;
	}

	private function owner(): AppAdmin
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getId')->willReturn(Uuid::uuid4());
		return $owner;
	}

}
