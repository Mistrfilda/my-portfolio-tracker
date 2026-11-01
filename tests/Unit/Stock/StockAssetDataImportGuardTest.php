<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock;

use App\Statistic\PortfolioStatisticCache;
use App\Stock\Asset\Download\StockAssetDataImportGuard;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Asset\StockAsset;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class StockAssetDataImportGuardTest extends TestCase
{

	#[TestWith([StockAssetDataType::PRICE])]
	#[TestWith([StockAssetDataType::DIVIDENDS])]
	public function testInvalidatesMarketCacheAfterTheImportCommits(StockAssetDataType $type): void
	{
		$committed = false;
		$imported = false;
		$em = $this->createStub(EntityManagerInterface::class);
		$em->method('wrapInTransaction')->willReturnCallback(
			static function (callable $operation) use (&$committed): bool {
				$result = $operation();
				$committed = true;
				return $result;
			},
		);
		$cache = $this->createMock(PortfolioStatisticCache::class);
		$cache->expects(self::once())->method(
			'invalidateAll',
		)->willReturnCallback(
			static function () use (&$committed): void {
				self::assertTrue($committed);
			},
		);
		$guard = new StockAssetDataImportGuard($em, $cache);
		$guard->import(
			$this->createStub(StockAsset::class),
			$type,
			new ImmutableDateTime('2026-01-02'),
			static function () use (&$imported): void {
				$imported = true;
			},
		);
		self::assertTrue($imported);
	}

	public function testOlderImportKeepsTheCacheAndDoesNotRunTheImporter(): void
	{
		$asset = $this->createStub(StockAsset::class);
		$asset->method('getPriceDownloadedAt')->willReturn(new ImmutableDateTime('2026-01-03'));
		$em = $this->createStub(EntityManagerInterface::class);
		$em->method('wrapInTransaction')->willReturnCallback(static fn (callable $operation): mixed => $operation());
		$cache = $this->createMock(PortfolioStatisticCache::class);
		$cache->expects(self::never())->method('invalidateAll');
		(new StockAssetDataImportGuard($em, $cache))->import(
			$asset,
			StockAssetDataType::PRICE,
			new ImmutableDateTime('2026-01-02'),
			static fn () => self::fail('An older import must be skipped.'),
		);
	}

	public function testFailedTransactionDoesNotInvalidateTheCommittedCache(): void
	{
		$em = $this->createStub(EntityManagerInterface::class);
		$em->method('wrapInTransaction')->willThrowException(new RuntimeException('Rolled back'));
		$cache = $this->createMock(PortfolioStatisticCache::class);
		$cache->expects(self::never())->method('invalidateAll');
		$this->expectException(RuntimeException::class);
		(new StockAssetDataImportGuard($em, $cache))->import(
			$this->createStub(StockAsset::class),
			StockAssetDataType::PRICE,
			new ImmutableDateTime('2026-01-02'),
			static fn () => null,
		);
	}

}
