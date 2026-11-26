<?php

declare(strict_types = 1);

namespace App\Test\Unit\Mcp\Tool;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Mcp\Support\McpInvalidInputException;
use App\Mcp\Support\StockAssetResolver;
use App\Mcp\Tool\SimulateStockPurchaseTool;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Stock\Position\Simulation\StockPurchaseSimulationService;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use const DATE_ATOM;

class SimulateStockPurchaseToolTest extends TestCase
{

	public function testFormatsSimulationPayload(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$currentAdminGetter = self::createStub(CurrentAppAdminGetter::class);
		$currentAdminGetter->method('getAppAdmin')->willReturn($owner);

		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'CEZ',
			StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER,
			'CEZ',
			StockAssetExchange::PRAGUE_STOCK_EXCHANGE,
			CurrencyEnum::CZK,
			$now,
			null,
			null,
			15.0,
			CurrencyEnum::CZK,
			true,
			false,
			false,
		);
		$asset->setCurrentPrice(
			new StockAssetPriceRecord(
				$now,
				CurrencyEnum::CZK,
				1000.0,
				$asset,
				StockAssetPriceDownloaderEnum::PRAGUE_EXCHANGE_DOWNLOADER,
				$now,
			),
			$now,
		);

		$assetRepository = self::createStub(StockAssetRepository::class);
		$assetRepository->method('findByTickerCaseInsensitive')->willReturn($asset);
		$assetRepository->method('findWithPositionsForAppAdmin')->willReturn([]);

		$portfolioProvider = self::createStub(StockPortfolioProvider::class);
		$portfolioProvider->method('getPortfolio')->willReturn([
			'generatedAt' => $now->format(DATE_ATOM),
			'currency' => 'CZK',
			'total' => ['investedAmount' => 75000.0, 'currentValue' => 75000.0, 'profit' => 0.0, 'profitPercent' => 0.0],
			'items' => [],
		]);

		$valuationDataRepository = self::createStub(StockValuationDataRepository::class);
		$valuationDataRepository->method('findTypesLatestForStockAsset')->willReturn([]);

		$service = new StockPurchaseSimulationService(
			$datetimeFactory,
			$portfolioProvider,
			$assetRepository,
			self::createStub(CurrencyConversionFacade::class),
			$valuationDataRepository,
			self::createStub(StockAssetDividendDetailService::class),
		);

		$tool = new SimulateStockPurchaseTool($currentAdminGetter, new StockAssetResolver($assetRepository), $service);
		$result = $tool->execute(ticker: 'CEZ', amount: 25000.0, amountCurrency: 'czk');

		self::assertTrue($result['readOnlySimulation']);
		self::assertIsArray($result['simulation']);
		self::assertSame(25.0, $result['simulation']['simulatedShares']);
		self::assertSame(25, $result['simulation']['wholeShares']);
		self::assertIsArray($result['after']);
		self::assertSame(25.0, $result['after']['positionWeightPercent']);
	}

	public function testRejectsUnsupportedCurrency(): void
	{
		$tool = new SimulateStockPurchaseTool(
			self::createStub(CurrentAppAdminGetter::class),
			new StockAssetResolver(self::createStub(StockAssetRepository::class)),
			new StockPurchaseSimulationService(
				self::createStub(DatetimeFactory::class),
				self::createStub(StockPortfolioProvider::class),
				self::createStub(StockAssetRepository::class),
				self::createStub(CurrencyConversionFacade::class),
				self::createStub(StockValuationDataRepository::class),
				self::createStub(StockAssetDividendDetailService::class),
			),
		);

		$this->expectException(McpInvalidInputException::class);
		$tool->execute(ticker: 'MSFT', amount: 1000.0, amountCurrency: 'JPY');
	}

}
