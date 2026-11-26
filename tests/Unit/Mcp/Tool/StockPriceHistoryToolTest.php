<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Mcp\Tool\StockPriceHistoryTool;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockPriceHistoryToolTest extends TestCase
{

	public function testDownsamplesLargeSeriesAndPreservesFirstAndLastPointAnd52WeekStats(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'Apple',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'AAPL',
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
		$asset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				CurrencyEnum::USD,
				180.0,
				$asset,
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				$now,
			),
			$now,
		);

		$records = [];
		for ($i = 0; $i < 200; $i++) {
			$date = $now->deductDaysFromDatetime(200 - $i);
			$price = 100.0 + ($i === 100 ? 100.0 : $i * 0.4);
			$records[] = new StockAssetPriceRecord(
				$date,
				CurrencyEnum::USD,
				$price,
				$asset,
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				$now,
			);
		}

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);

		$priceRepository = self::createStub(StockAssetPriceRecordRepository::class);
		$priceRepository->method('findByStockAssetSinceDate')->willReturn($records);

		$tool = new StockPriceHistoryTool(
			new StockAssetResolver($assetRepository),
			$priceRepository,
			$datetimeFactory,
		);

		$result = $tool->execute(ticker: 'AAPL', period: 'ALL', maxPoints: 20);

		self::assertSame('ALL', $result['period']);
		self::assertIsArray($result['points']);
		self::assertCount(20, $result['points']);
		self::assertIsArray($result['points'][0]);
		self::assertIsArray($result['points'][19]);
		self::assertSame($records[0]->getDate()->format('Y-m-d'), $result['points'][0]['date']);
		self::assertSame($records[199]->getDate()->format('Y-m-d'), $result['points'][19]['date']);

		self::assertIsArray($result['fiftyTwoWeek']);
		self::assertSame(200.0, $result['fiftyTwoWeek']['high']);
		self::assertSame(100.0, $result['fiftyTwoWeek']['low']);
		self::assertSame(-10.0, $result['fiftyTwoWeek']['distanceFromHighPercent']);
		self::assertSame(80.0, $result['fiftyTwoWeek']['distanceFromLowPercent']);
	}

	public function testRejectsUnsupportedPeriod(): void
	{
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-05-01 12:00:00'));

		$tool = new StockPriceHistoryTool(
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			self::createStub(StockAssetPriceRecordRepository::class),
			$datetimeFactory,
		);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(ticker: 'AAPL', period: '5Y');
	}

}
