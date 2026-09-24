<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Asset\Download;

use App\Stock\Asset\Download\StockAssetDataFreshness;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockAssetDataFreshnessTest extends TestCase
{

	#[DataProvider('cycles')]
	public function testScheduledCutoff(string $now, StockAssetDataType $type, string $expected): void
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime($now));
		$policy = new StockAssetDataFreshness($clock, []);
		$this->assertSame($expected, $policy->getCutoff($type)->format('Y-m-d H:i:s'));
	}

	/** @return iterable<string, array{string, StockAssetDataType, string}> */
	public static function cycles(): iterable
	{
		yield 'Monday before first cycle' => ['2026-09-21 12:24:00', StockAssetDataType::PRICE, '2026-09-18 22:25:00'];
		yield 'retries stay in completion grace' => ['2026-09-21 12:46:00', StockAssetDataType::PRICE, '2026-09-18 22:25:00'];
		yield 'missing cron still advances cutoff' => ['2026-09-21 13:10:00', StockAssetDataType::PRICE, '2026-09-21 12:25:00'];
		yield 'weekend keeps Friday cycle' => ['2026-09-20 16:00:00', StockAssetDataType::PRICE, '2026-09-18 22:25:00'];
		yield 'Tuesday dividends' => ['2026-09-22 12:00:00', StockAssetDataType::DIVIDENDS, '2026-09-21 06:00:00'];
		yield 'Wednesday dividends' => ['2026-09-23 06:45:00', StockAssetDataType::DIVIDENDS, '2026-09-23 06:00:00'];
		yield 'weekly valuation grace' => ['2026-09-26 06:30:00', StockAssetDataType::VALUATION, '2026-09-19 06:00:00'];
		yield 'weekly analyst cutoff' => ['2026-09-26 06:45:00', StockAssetDataType::ANALYST_INSIGHTS, '2026-09-26 06:00:00'];
	}

	public function testDownloaderThresholdAndNewAssetGrace(): void
	{
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn(new ImmutableDateTime('2026-09-21 13:10:00'));
		$policy = new StockAssetDataFreshness($clock, ['TWELVE_DATA' => 2, 'WEB_SCRAP' => 1]);
		$this->assertSame('2026-09-21 10:25:00', $policy->getCutoff(
			StockAssetDataType::PRICE,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
		)->format('Y-m-d H:i:s'));
		$this->assertSame('2026-09-21 11:25:00', $policy->getCutoff(
			StockAssetDataType::PRICE,
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
		)->format('Y-m-d H:i:s'));
		$this->assertSame('2026-09-21 12:55:00', $policy->getInitialDownloadDeadline()->format('Y-m-d H:i:s'));
	}

}
