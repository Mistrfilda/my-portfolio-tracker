<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Support;

use App\Currency\CurrencyEnum;
use App\Doctrine\NoEntityFoundException;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StockAssetResolverTest extends TestCase
{

	public function testResolvesByTickerCaseInsensitive(): void
	{
		$asset = $this->createAsset('AAPL');
		$repository = $this->createMock(StockAssetRepository::class);
		$repository->expects(self::once())
			->method('findByTickerCaseInsensitive')
			->with('aapl')
			->willReturn($asset);

		$resolver = new StockAssetResolver($repository);
		self::assertSame($asset, $resolver->resolve(ticker: ' aapl '));
	}

	public function testResolvesByUuid(): void
	{
		$asset = $this->createAsset('MSFT');
		$id = $asset->getId();
		$repository = $this->createMock(StockAssetRepository::class);
		$repository->expects(self::once())
			->method('getById')
			->with(self::callback(static fn (UuidInterface $uuid): bool => $uuid->equals($id)))
			->willReturn($asset);

		$resolver = new StockAssetResolver($repository);
		self::assertSame($asset, $resolver->resolve(stockAssetId: $id->toString()));
	}

	public function testThrowsWhenBothInputsMissing(): void
	{
		$resolver = new StockAssetResolver(self::createStub(StockAssetRepository::class));
		$this->expectException(McpInvalidInputException::class);
		$resolver->resolve();
	}

	public function testThrowsOnInvalidUuid(): void
	{
		$resolver = new StockAssetResolver(self::createStub(StockAssetRepository::class));
		$this->expectException(McpInvalidInputException::class);
		$resolver->resolve(stockAssetId: 'not-a-uuid');
	}

	public function testThrowsWhenIdNotFound(): void
	{
		$repository = self::createStub(StockAssetRepository::class);
		$repository->method('getById')->willThrowException(new NoEntityFoundException());

		$resolver = new StockAssetResolver($repository);
		$this->expectException(McpInvalidInputException::class);
		$resolver->resolve(stockAssetId: Uuid::uuid4()->toString());
	}

	public function testThrowsWhenTickerNotFound(): void
	{
		$repository = self::createStub(StockAssetRepository::class);
		$repository->method('findByTickerCaseInsensitive')->willReturn(null);

		$resolver = new StockAssetResolver($repository);
		$this->expectException(McpInvalidInputException::class);
		$resolver->resolve(ticker: 'UNKNOWN');
	}

	private function createAsset(string $ticker): StockAsset
	{
		return new StockAsset(
			$ticker . ' Inc.',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			$ticker,
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			new ImmutableDateTime('2026-01-01 00:00:00'),
			null,
			null,
			null,
			null,
			true,
			true,
			false,
		);
	}

}
