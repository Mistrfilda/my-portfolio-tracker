<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Admin\AppAdmin;
use App\Statistic\PortfolioStatisticCache;
use Doctrine\DBAL\Connection;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Caching\Storages\MemoryStorage;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class PortfolioStatisticCacheTest extends TestCase
{

	public function testSharesResultsAcrossInstancesAndInvalidatesOnlyTheChangedOwner(): void
	{
		$storage = new MemoryStorage();
		$web = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$worker = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$ownerA = $this->owner();
		$ownerB = $this->owner();
		self::assertSame('A', $web->load($ownerA, 'summary', static fn (): string => 'A'));
		self::assertSame('B', $worker->load($ownerB, 'summary', static fn (): string => 'B'));
		self::assertSame('A', $worker->load($ownerA, 'summary', static fn (): string => 'unexpected'));

		$worker->invalidate($ownerA);
		self::assertSame('updated A', $web->load($ownerA, 'summary', static fn (): string => 'updated A'));
		self::assertSame('B', $web->load($ownerB, 'summary', static fn (): string => 'unexpected'));

		$worker->invalidateAll();
		self::assertSame('new A', $web->load($ownerA, 'summary', static fn (): string => 'new A'));
		self::assertSame('new B', $web->load($ownerB, 'summary', static fn (): string => 'new B'));
	}

	public function testInvalidationDuringCalculationCannotRestoreTheOldResult(): void
	{
		$storage = new MemoryStorage();
		$web = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$worker = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$owner = $this->owner();
		self::assertSame('old', $web->load($owner, 'summary', static function () use ($worker, $owner): string {
			$worker->invalidate($owner);
			$worker->load($owner, 'summary', static fn (): string => 'new');
			return 'old';
		}));
		self::assertSame('new', $web->load($owner, 'summary', static fn (): string => 'unexpected'));
	}

	public function testMarketInvalidationDuringCalculationCannotRestoreTheOldResult(): void
	{
		$storage = new MemoryStorage();
		$web = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$worker = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		$owner = $this->owner();
		$web->load($owner, 'summary', static function () use ($worker): string {
			$worker->invalidateAll();
			return 'old';
		});
		self::assertSame('new', $web->load($owner, 'summary', static fn (): string => 'new'));
	}

	public function testTransactionReadsBypassSharedCacheAndNeverPublishUncommittedResults(): void
	{
		$connection = $this->createStub(Connection::class);
		$connection->method('isTransactionActive')->willReturn(false, true, true, false);
		$cache = new PortfolioStatisticCache(new MemoryStorage(), $connection);
		$owner = $this->owner();
		self::assertSame('committed', $cache->load($owner, 'summary', static fn (): string => 'committed'));

		self::assertSame('uncommitted', $cache->load($owner, 'summary', static fn (): string => 'uncommitted'));
		self::assertSame('changed again', $cache->load($owner, 'summary', static fn (): string => 'changed again'));

		self::assertSame('committed', $cache->load($owner, 'summary', static fn (): string => 'unexpected'));
	}

	public function testCachesEmptyResultsAndKeepsASevenDayExpiryAsFallback(): void
	{
		$storage = $this->createMock(Storage::class);
		$storage->method('read')->willReturn(null);
		$writes = [];
		$storage->expects(self::exactly(3))->method('write')->willReturnCallback(
			static function (string $key, mixed $data, array $dependencies) use (&$writes): void {
				$writes[] = [$data, $dependencies];
			},
		);
		$cache = new PortfolioStatisticCache($storage, $this->createStub(Connection::class));
		self::assertSame([], $cache->load($this->owner(), 'groups', static fn (): array => []));
		self::assertSame([[], [Cache::Expire => 604800]], $writes[2]);
	}

	private function owner(): AppAdmin
	{
		$owner = $this->createStub(AppAdmin::class);
		$owner->method('getId')->willReturn(Uuid::uuid4());
		return $owner;
	}

}
