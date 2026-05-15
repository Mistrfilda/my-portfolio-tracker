<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Price\Downloader;

use App\Stock\Asset\StockAsset;
use App\Stock\Price\Downloader\Json\JsonWebDataService;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class JsonWebDataServiceTest extends TestCase
{

	public function testGenerateStockAssetUrls(): void
	{
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$stockAsset = $this->createStockAsset('AAPL');
		$service = new JsonWebDataService(
			'https://example.test/quote/%s',
			'https://example.test/dividends/%s?from=%s&to=%s',
			'https://example.test/financials/%s',
			'https://example.test/key-statistics/%s',
			'https://example.test/industry',
			'https://example.test/analyst-insights/%s',
			$datetimeFactory,
		);

		self::assertSame('https://example.test/quote/AAPL', $service->getStockAssetPriceUrl($stockAsset));
		self::assertSame('https://example.test/financials/AAPL', $service->getFinancialsDataUrl($stockAsset));
		self::assertSame('https://example.test/key-statistics/AAPL', $service->getKeyStatisticsDataUrl($stockAsset));
		self::assertSame('https://example.test/analyst-insights/AAPL', $service->getAnalystInsightUrl($stockAsset));
		self::assertSame('https://example.test/industry', $service->getStockAssetIndustryUrl());
	}

	public function testGenerateStockAssetDividendsUrlUsesFiveYearDateRangeUntilYesterday(): void
	{
		$today = new ImmutableDateTime('2026-05-15 00:00:00');
		$datetimeFactory = $this->createMock(DatetimeFactory::class);
		$datetimeFactory->expects($this->exactly(2))
			->method('createToday')
			->willReturn($today);
		$stockAsset = $this->createStockAsset('MSFT');
		$service = new JsonWebDataService(
			'https://example.test/quote/%s',
			'https://example.test/dividends/%s?from=%s&to=%s',
			'https://example.test/financials/%s',
			'https://example.test/key-statistics/%s',
			'https://example.test/industry',
			'https://example.test/analyst-insights/%s',
			$datetimeFactory,
		);

		self::assertSame(
			sprintf(
				'https://example.test/dividends/MSFT?from=%s&to=%s',
				$today->deductYearsFromDatetime(5)->getTimestamp(),
				$today->deductDaysFromDatetime(1)->getTimestamp(),
			),
			$service->getStockAssetDividendsUrl($stockAsset),
		);
	}

	private function createStockAsset(string $ticker): StockAsset
	{
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getTicker')->willReturn($ticker);

		return $stockAsset;
	}

}
