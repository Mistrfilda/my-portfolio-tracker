<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Tool\SearchStocksTool;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class SearchStocksToolTest extends TestCase
{

	public function testSearchesAndMarksPortfolioWatchlistAndValuationFlags(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$held = new StockAsset(
			'Microsoft',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'MSFT',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			true,
			false,
		);
		$held->markValuationDownloaded($now);

		$watched = new StockAsset(
			'Microchip Technology',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'MCHP',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			true,
			true,
		);

		$repository = $this->createMock(StockAssetRepository::class);
		$repository->expects(self::once())
			->method('findWithPositionsForAppAdmin')
			->with($owner, true)
			->willReturn([$held]);
		$repository->expects(self::once())
			->method('search')
			->with('Micro', null, 10)
			->willReturn([$held, $watched]);

		$tool = new SearchStocksTool($currentAdminGetter, $datetimeFactory, $repository);
		$result = $tool->execute(query: ' Micro ', limit: 10);

		self::assertSame(2, $result['count']);
		self::assertIsArray($result['items']);
		self::assertIsArray($result['items'][0]);
		self::assertTrue($result['items'][0]['inPortfolio']);
		self::assertFalse($result['items'][0]['inWatchlist']);
		self::assertTrue($result['items'][0]['hasValuation']);
		self::assertIsArray($result['items'][1]);
		self::assertFalse($result['items'][1]['inPortfolio']);
		self::assertTrue($result['items'][1]['inWatchlist']);
		self::assertFalse($result['items'][1]['hasValuation']);
	}

	public function testRejectsEmptyQueryAndSector(): void
	{
		$tool = new SearchStocksTool(
			self::createStub(CurrentAppAdminGetter::class),
			self::createStub(DatetimeFactory::class),
			self::createStub(StockAssetRepository::class),
		);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(query: '   ', sector: null);
	}

}
