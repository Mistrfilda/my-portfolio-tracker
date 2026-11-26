<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Mcp\Tool\StockDividendsTool;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Forecast\StockAssetDividendRemainingForecastProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\Record\StockAssetDividendYearSummaryDTO;
use App\Stock\Dividend\Safety\StockDividendSafetyScore;
use App\Stock\Dividend\Safety\StockDividendSafetyScoreProvider;
use App\Stock\Dividend\Safety\StockDividendSafetyScoreStatusEnum;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Dividend\StockAssetDividendRepository;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Dividend\UI\StockAssetDividendDetailDTO;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class StockDividendsToolTest extends TestCase
{

	public function testReturnsStockDividendsWithUpcomingAndSafetyScore(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'Johnson & Johnson',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'JNJ',
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$now,
			null,
			StockAssetDividendSourceEnum::WEB,
			15.0,
			CurrencyEnum::USD,
			true,
			true,
			false,
		);
		$asset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				CurrencyEnum::USD,
				160.0,
				$asset,
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				$now,
			),
			$now,
		);

		$pastDividend = new StockAssetDividend(
			$asset,
			new ImmutableDateTime('2026-02-15 00:00:00'),
			new ImmutableDateTime('2026-03-01 00:00:00'),
			null,
			CurrencyEnum::USD,
			1.24,
			$now,
		);
		$upcomingDividend = new StockAssetDividend(
			$asset,
			new ImmutableDateTime('2026-05-20 00:00:00'),
			new ImmutableDateTime('2026-06-05 00:00:00'),
			null,
			CurrencyEnum::USD,
			1.30,
			$now,
		);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);

		$currencyFacade = self::createStub(CurrencyConversionFacade::class);
		$currencyFacade->method('getConvertedSummaryPrice')->willReturn(new SummaryPrice(CurrencyEnum::CZK, 100.0));

		$detailService = self::createStub(StockAssetDividendDetailService::class);
		$detailService->method('getMarketDetailDTOFromDate')->willReturn(
			new StockAssetDividendDetailDTO($currencyFacade, $asset, [], [$pastDividend, $upcomingDividend]),
		);

		$dividendRepository = self::createStub(StockAssetDividendRepository::class);
		$dividendRepository->method('findByStockAsset')->willReturn([$pastDividend, $upcomingDividend]);

		$recordRepository = self::createStub(StockAssetDividendRecordRepository::class);
		$recordRepository->method('findByStockAsset')->willReturn([]);

		$recordFacade = self::createStub(StockAssetDividendRecordFacade::class);
		$recordFacade->method('getTotalSummaryPriceForStockAsset')->willReturn(null);

		$safetyProvider = self::createStub(StockDividendSafetyScoreProvider::class);
		$safetyProvider->method('getForStockAsset')->willReturn(
			new StockDividendSafetyScore(92, StockDividendSafetyScoreStatusEnum::SAFE, ['Výplatní poměr' => 'OK']),
		);

		$valuationDataRepository = self::createStub(StockValuationDataRepository::class);
		$valuationDataRepository->method('findTypesLatestForStockAsset')->willReturn([]);

		$tool = new StockDividendsTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver($assetRepository),
			$detailService,
			$dividendRepository,
			$recordRepository,
			$recordFacade,
			self::createStub(StockAssetDividendRemainingForecastProvider::class),
			$safetyProvider,
			$valuationDataRepository,
			$currencyFacade,
		);

		$result = $tool->execute(scope: 'stock', ticker: 'JNJ');

		self::assertSame('stock', $result['scope']);
		self::assertIsArray($result['upcomingDividends']);
		self::assertCount(1, $result['upcomingDividends']);
		self::assertIsArray($result['recentDividends']);
		self::assertCount(1, $result['recentDividends']);
		self::assertIsArray($result['safetyScore']);
		self::assertSame(92, $result['safetyScore']['score']);
		self::assertSame('safe', $result['safetyScore']['status']);
	}

	public function testReturnsPortfolioDividendsSummary(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$recordFacade = self::createStub(StockAssetDividendRecordFacade::class);
		$recordFacade->method('getTotalSummaryPrice')->willReturn(new SummaryPrice(CurrencyEnum::CZK, 45000.0));
		$recordFacade->method('getDividendsByYears')->willReturn([
			new StockAssetDividendYearSummaryDTO(
				2026,
				new SummaryPrice(CurrencyEnum::CZK, 12000.0),
				new SummaryPrice(CurrencyEnum::CZK, 14100.0),
			),
		]);

		$forecastProvider = self::createStub(StockAssetDividendRemainingForecastProvider::class);
		$forecastProvider->method('getForYear')->willReturn(
			new StockAssetDividendYearSummaryDTO(
				2026,
				new SummaryPrice(CurrencyEnum::CZK, 18000.0),
				new SummaryPrice(CurrencyEnum::CZK, 21000.0),
			),
		);

		$recordRepository = self::createStub(StockAssetDividendRecordRepository::class);
		$recordRepository->method('findByYearAndMonth')->willReturn([]);
		$recordRepository->method('findLastDividendRecords')->willReturn([]);

		$tool = new StockDividendsTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			self::createStub(StockAssetDividendDetailService::class),
			self::createStub(StockAssetDividendRepository::class),
			$recordRepository,
			$recordFacade,
			$forecastProvider,
			self::createStub(StockDividendSafetyScoreProvider::class),
			self::createStub(StockValuationDataRepository::class),
			self::createStub(CurrencyConversionFacade::class),
		);

		$result = $tool->execute(scope: 'portfolio');

		self::assertSame('portfolio', $result['scope']);
		self::assertSame(45000.0, $result['totalReceivedNetCzk']);
		self::assertSame([
			'year' => 2026,
			'remainingNetCzk' => 18000.0,
			'remainingGrossCzk' => 21000.0,
		], $result['remainingForecastCurrentYear']);
		self::assertSame([
			['year' => 2026, 'netCzk' => 12000.0, 'grossCzk' => 14100.0],
		], $result['yearlySummary']);
	}

	public function testRejectsInvalidScope(): void
	{
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn(self::createStub(AppAdmin::class));
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-05-01 12:00:00'));

		$tool = new StockDividendsTool(
			$currentAdminGetter,
			$datetimeFactory,
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			self::createStub(StockAssetDividendDetailService::class),
			self::createStub(StockAssetDividendRepository::class),
			self::createStub(StockAssetDividendRecordRepository::class),
			self::createStub(StockAssetDividendRecordFacade::class),
			self::createStub(StockAssetDividendRemainingForecastProvider::class),
			self::createStub(StockDividendSafetyScoreProvider::class),
			self::createStub(StockValuationDataRepository::class),
			self::createStub(CurrencyConversionFacade::class),
		);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(scope: 'invalid');
	}

}
