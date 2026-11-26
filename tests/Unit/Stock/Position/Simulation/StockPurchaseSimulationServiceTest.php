<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Position\Simulation;

use App\Admin\AppAdmin;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Stock\Asset\Industry\StockAssetIndustry;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetExchange;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\UI\StockAssetDividendDetailService;
use App\Stock\Position\Api\StockPortfolioProvider;
use App\Stock\Position\Simulation\StockPurchaseSimulationException;
use App\Stock\Position\Simulation\StockPurchaseSimulationService;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Valuation\Data\StockValuationData;
use App\Stock\Valuation\Data\StockValuationDataRepository;
use App\Stock\Valuation\StockValuationTypeEnum;
use App\Stock\Valuation\StockValuationTypeGroupEnum;
use App\Stock\Valuation\StockValuationTypeValueTypeEnum;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use const DATE_ATOM;

class StockPurchaseSimulationServiceTest extends TestCase
{

	public function testSimulatesAddingToExistingUsdPositionFromCzkAmount(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$tech = new StockAssetIndustry(
			'Technology',
			'Technology',
			$now,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		);
		$asset = new StockAsset(
			'Microsoft',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'MSFT',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			15.0,
			CurrencyEnum::USD,
			true,
			true,
			false,
			$tech,
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

		$otherTech = new StockAsset(
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
			$tech,
		);

		$portfolioProvider = self::createStub(StockPortfolioProvider::class);
		$portfolioProvider->method('getPortfolio')->willReturn([
			'generatedAt' => $now->format(DATE_ATOM),
			'currency' => 'CZK',
			'total' => [
				'investedAmount' => 150000.0,
				'currentValue' => 200000.0,
				'profit' => 50000.0,
				'profitPercent' => 33.33,
			],
			'items' => [
				[
					'stock' => ['id' => $asset->getId()->toString(), 'ticker' => 'MSFT', 'currency' => 'USD'],
					'pieces' => 10,
					'averagePurchasePrice' => 300.0,
					'currentValueCzk' => 100000.0,
				],
				[
					'stock' => ['id' => $otherTech->getId()->toString(), 'ticker' => 'AAPL', 'currency' => 'USD'],
					'pieces' => 5,
					'averagePurchasePrice' => 200.0,
					'currentValueCzk' => 50000.0,
				],
			],
		]);

		$stockAssetRepository = self::createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findWithPositionsForAppAdmin')->willReturn([$asset, $otherTech]);

		$currencyFacade = self::createStub(CurrencyConversionFacade::class);
		$currencyFacade->method('convertSimpleValue')->willReturnCallback(
			static function (float $value, CurrencyEnum $from, CurrencyEnum $to): float {
				if ($from === CurrencyEnum::USD && $to === CurrencyEnum::CZK) {
					return $value * 25.0;
				}

				if ($from === CurrencyEnum::CZK && $to === CurrencyEnum::USD) {
					return $value / 25.0;
				}

				return $value;
			},
		);

		$forwardDividend = new StockValuationData(
			$asset,
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE,
			StockValuationTypeGroupEnum::DIVIDENDS,
			StockValuationTypeValueTypeEnum::FLOAT,
			$now,
			null,
			4.0,
			CurrencyEnum::USD,
			$now,
		);
		$valuationDataRepository = self::createStub(StockValuationDataRepository::class);
		$valuationDataRepository->method('findTypesLatestForStockAsset')->willReturn([
			StockValuationTypeEnum::FORWARD_ANNUAL_DIVIDEND_RATE->value => $forwardDividend,
		]);

		$service = new StockPurchaseSimulationService(
			$datetimeFactory,
			$portfolioProvider,
			$stockAssetRepository,
			$currencyFacade,
			$valuationDataRepository,
			self::createStub(StockAssetDividendDetailService::class),
		);

		// 50 000 CZK @ 25 CZK/USD = 2 000 USD -> 5 shares @ 400 USD
		$result = $service->simulate($owner, $asset, 50000.0, CurrencyEnum::CZK, null);

		self::assertEqualsWithDelta(5.0, $result->simulatedShares, 0.0001);
		self::assertEqualsWithDelta(2000.0, $result->investedAmountInStockCurrency, 0.01);
		self::assertEqualsWithDelta(50000.0, $result->investedAmountInCzk, 0.01);

		// Existing: 10 shares @ 300 USD -> Projected: 15 shares @ (3000 + 2000) / 15 = 333.3333 USD
		self::assertSame(10, $result->existingShares);
		self::assertEqualsWithDelta(15.0, $result->projectedShares, 0.0001);
		self::assertEqualsWithDelta(333.3333, $result->projectedAveragePurchasePrice, 0.001);

		// Portfolio: 200k -> 250k CZK; MSFT: 100k (50%) -> 150k (60%); Tech sector: 150k (75%) -> 200k (80%)
		self::assertEqualsWithDelta(50.0, $result->existingPositionWeightPercent, 0.01);
		self::assertEqualsWithDelta(60.0, $result->projectedPositionWeightPercent, 0.01);
		self::assertEqualsWithDelta(75.0, $result->existingSectorWeightPercent, 0.01);
		self::assertEqualsWithDelta(80.0, $result->projectedSectorWeightPercent, 0.01);

		// Annual dividend: 5 * 4 USD = 20 USD gross, 17 USD net (15% tax), 425 CZK net
		self::assertSame('forward_annual_dividend_rate', $result->dividendRateSource);
		self::assertEqualsWithDelta(20.0, $result->estimatedAnnualGrossDividendInStockCurrency, 0.01);
		self::assertEqualsWithDelta(17.0, $result->estimatedAnnualNetDividendInStockCurrency, 0.01);
		self::assertEqualsWithDelta(425.0, $result->estimatedAnnualNetDividendCzk, 0.01);
		self::assertSame([], $result->warnings);
	}

	public function testSimulatesNewWatchlistPositionBySharesAndPriceOverride(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$healthcare = new StockAssetIndustry(
			'Healthcare',
			'Healthcare',
			$now,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
		);
		$asset = new StockAsset(
			'Novo Nordisk',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'NVO',
			StockAssetExchange::NYSE,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			false,
			true,
			$healthcare,
		);

		$portfolioProvider = self::createStub(StockPortfolioProvider::class);
		$portfolioProvider->method('getPortfolio')->willReturn([
			'generatedAt' => $now->format(DATE_ATOM),
			'currency' => 'CZK',
			'total' => ['investedAmount' => 100000.0, 'currentValue' => 100000.0, 'profit' => 0.0, 'profitPercent' => 0.0],
			'items' => [],
		]);

		$stockAssetRepository = self::createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findWithPositionsForAppAdmin')->willReturn([]);

		$currencyFacade = self::createStub(CurrencyConversionFacade::class);
		$currencyFacade->method('convertSimpleValue')->willReturn(25.0);

		$valuationDataRepository = self::createStub(StockValuationDataRepository::class);
		$valuationDataRepository->method('findTypesLatestForStockAsset')->willReturn([]);

		$service = new StockPurchaseSimulationService(
			$datetimeFactory,
			$portfolioProvider,
			$stockAssetRepository,
			$currencyFacade,
			$valuationDataRepository,
			self::createStub(StockAssetDividendDetailService::class),
		);

		// 10 shares @ 100 USD override = 1000 USD = 25 000 CZK -> new portfolio 125 000 CZK -> 20% weight
		$result = $service->simulate($owner, $asset, null, null, 10.0, 100.0);

		self::assertTrue($result->usedPriceOverride);
		self::assertSame(0, $result->existingShares);
		self::assertNull($result->existingAveragePurchasePrice);
		self::assertEqualsWithDelta(10.0, $result->projectedShares, 0.0001);
		self::assertEqualsWithDelta(100.0, $result->projectedAveragePurchasePrice, 0.0001);
		self::assertEqualsWithDelta(20.0, $result->projectedPositionWeightPercent, 0.01);
		self::assertEqualsWithDelta(20.0, $result->projectedSectorWeightPercent, 0.01);
		self::assertNull($result->estimatedAnnualNetDividendCzk);
	}

	public function testThrowsOnMissingExchangeRate(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
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
			false,
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

		$currencyFacade = self::createStub(CurrencyConversionFacade::class);
		$currencyFacade->method('convertSimpleValue')->willThrowException(new MissingCurrencyPairException());

		$service = new StockPurchaseSimulationService(
			$datetimeFactory,
			self::createStub(StockPortfolioProvider::class),
			self::createStub(StockAssetRepository::class),
			$currencyFacade,
			self::createStub(StockValuationDataRepository::class),
			self::createStub(StockAssetDividendDetailService::class),
		);

		$this->expectException(StockPurchaseSimulationException::class);
		$service->simulate($owner, $asset, 25000.0, CurrencyEnum::CZK, null);
	}

	public function testThrowsOnMissingPriceAndNoOverride(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
		$datetimeFactory = self::createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);

		$asset = new StockAsset(
			'NoPrice',
			StockAssetPriceDownloaderEnum::WEB_SCRAP,
			'NOP',
			StockAssetExchange::NASDAQ,
			CurrencyEnum::USD,
			$now,
			null,
			null,
			null,
			null,
			true,
			false,
			false,
		);

		$service = new StockPurchaseSimulationService(
			$datetimeFactory,
			self::createStub(StockPortfolioProvider::class),
			self::createStub(StockAssetRepository::class),
			self::createStub(CurrencyConversionFacade::class),
			self::createStub(StockValuationDataRepository::class),
			self::createStub(StockAssetDividendDetailService::class),
		);

		$this->expectException(StockPurchaseSimulationException::class);
		$service->simulate($owner, $asset, 1000.0, CurrencyEnum::USD, null);
	}

	public function testThrowsWhenAmountWithoutCurrency(): void
	{
		$now = new ImmutableDateTime('2026-05-01 12:00:00');
		$owner = self::createStub(AppAdmin::class);
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
			false,
			false,
		);

		$service = new StockPurchaseSimulationService(
			self::createStub(DatetimeFactory::class),
			self::createStub(StockPortfolioProvider::class),
			self::createStub(StockAssetRepository::class),
			self::createStub(CurrencyConversionFacade::class),
			self::createStub(StockValuationDataRepository::class),
			self::createStub(StockAssetDividendDetailService::class),
		);

		$this->expectException(StockPurchaseSimulationException::class);
		$service->simulate($owner, $asset, 1000.0, null, null);
	}

}
