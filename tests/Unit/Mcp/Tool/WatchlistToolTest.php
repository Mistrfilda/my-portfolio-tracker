<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Asset\Price\AssetPrice;
use App\Currency\CurrencyEnum;
use App\Mcp\Tool\WatchlistTool;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Asset\Watchlist\StockAssetWatchlist;
use App\Stock\Asset\Watchlist\StockAssetWatchlistRepository;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\MarginOfSafety\StockValuationMarginOfSafetyProvider;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensus;
use App\Stock\Valuation\Model\Consensus\StockValuationModelConsensusConfidenceEnum;
use App\Stock\Valuation\StockValuationPriceProvider;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class WatchlistToolTest extends TestCase
{

	public function testCombinesFullAndSimpleWatchlistWithConsensusAndMarginOfSafety(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
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
			true,
		);
		$asset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				CurrencyEnum::USD,
				400.0,
				$asset,
				StockAssetPriceDownloaderEnum::WEB_SCRAP,
				$now,
			),
			$now,
		);

		$stockAssetRepository = self::createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findAllWatchlist')->willReturn([$asset]);

		$simpleItem = new StockAssetWatchlist($owner, 'Visa', 'V', 260.0, CurrencyEnum::USD, $now);
		$watchlistRepository = $this->createMock(StockAssetWatchlistRepository::class);
		$watchlistRepository->expects(self::once())
			->method('findAllForAppAdmin')
			->with($owner)
			->willReturn([$simpleItem]);

		$consensus = new StockValuationModelConsensus(
			price: new AssetPrice($asset, 480.0, CurrencyEnum::USD),
			percentage: 20.0,
			rawMedianPrice: null,
			arithmeticMeanPrice: null,
			lowerQuartilePrice: null,
			upperQuartilePrice: null,
			lowerQuartilePercentage: null,
			upperQuartilePercentage: null,
			normalizedIqrPercentage: null,
			normalizedMadPercentage: null,
			validModelsCount: 8,
			totalModelsCount: 10,
			validFamiliesCount: 4,
			totalFamiliesCount: 5,
			outlierModelLabels: [],
			familyEstimates: [],
			confidence: StockValuationModelConsensusConfidenceEnum::HIGH,
		);

		$priceProvider = self::createStub(StockValuationPriceProvider::class);
		$priceProvider->method('getModelConsensus')->willReturn($consensus);
		$priceProvider->method('getAnalyticsPrice')->willReturn(new AssetPrice($asset, 500.0, CurrencyEnum::USD));
		$priceProvider->method('getAiAnalysisPrice')->willReturn(new AssetPrice($asset, 490.0, CurrencyEnum::USD));

		$tool = new WatchlistTool(
			$currentAdminGetter,
			$datetimeFactory,
			$stockAssetRepository,
			$watchlistRepository,
			$priceProvider,
			new StockValuationMarginOfSafetyProvider(),
		);

		$result = $tool->execute(includeSimpleWatchlist: true);

		self::assertSame(1, $result['fullWatchlistCount']);
		self::assertSame(1, $result['simpleWatchlistCount']);
		self::assertIsArray($result['fullWatchlist']);
		self::assertIsArray($result['fullWatchlist'][0]);
		self::assertSame('MSFT', $result['fullWatchlist'][0]['ticker']);
		self::assertIsArray($result['fullWatchlist'][0]['marginOfSafety']);
		self::assertSame('undervalued', $result['fullWatchlist'][0]['marginOfSafety']['status']);
		self::assertIsArray($result['simpleWatchlist']);
		self::assertIsArray($result['simpleWatchlist'][0]);
		self::assertSame('V', $result['simpleWatchlist'][0]['ticker']);
		self::assertSame(260.0, $result['simpleWatchlist'][0]['recommendedEntryPrice']);
	}

}
