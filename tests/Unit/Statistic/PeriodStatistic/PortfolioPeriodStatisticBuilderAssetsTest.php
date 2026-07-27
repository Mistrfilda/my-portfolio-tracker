<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\PeriodStatistic;

use App\Asset\Price\AssetPrice;
use App\Asset\Price\SummaryPrice;
use App\Crypto\Asset\CryptoAsset;
use App\Crypto\Asset\CryptoAssetRepository;
use App\Crypto\Position\CryptoPosition;
use App\Crypto\Price\CryptoAssetPriceRecord;
use App\Crypto\Price\CryptoAssetPriceRecordRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Dashboard\DashboardValueGroupEnum;
use App\Portu\Asset\PortuAsset;
use App\Portu\Asset\PortuAssetRepository;
use App\Portu\Position\PortuPosition;
use App\Portu\Price\PortuAssetPriceRecord;
use App\Portu\Price\PortuAssetPriceRecordRepository;
use App\Statistic\Performance\PortfolioPerformanceIncome;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\Performance\PortfolioPerformanceSummary;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticBuilder;
use App\Statistic\PortfolioStatistic;
use App\Statistic\PortfolioStatisticControlTypeEnum;
use App\Statistic\PortfolioStatisticRecord;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\PortolioStatisticType;
use App\Stock\Asset\StockAsset;
use App\Stock\Asset\StockAssetRepository;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\StockPosition;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use App\Stock\Price\StockAssetPriceRecord;
use App\Stock\Price\StockAssetPriceRecordRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class PortfolioPeriodStatisticBuilderAssetsTest extends TestCase
{

	public function testBuildCalculatesStockBoundariesCashFlowsAndDividends(): void
	{
		$start = new ImmutableDateTime('2026-01-02 10:00:00');
		$end = new ImmutableDateTime('2026-01-30 20:00:00');
		$recordRepository = $this->createRecordRepository($start, $end);
		$assetId = Uuid::uuid4();
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getId')->willReturn($assetId);
		$stockAsset->method('getName')->willReturn('Acme');
		$stockAsset->method('getTicker')->willReturn('ACME');
		$stockAsset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$stockAsset->method('getDividendTax')->willReturn(20.0);

		$openPosition = $this->createStub(StockPosition::class);
		$openPosition->method('getOrderDate')->willReturn($start);
		$openPosition->method('getOrderPiecesCount')->willReturn(2);
		$openPosition->method('getStockClosedPosition')->willReturn(null);
		$openPosition->method('getTotalInvestedAmountInBrokerCurrency')->willReturn(
			new AssetPrice($stockAsset, 100.0, CurrencyEnum::USD),
		);

		$closedPosition = $this->createStub(StockPosition::class);
		$closedPosition->method('getOrderDate')->willReturn(new ImmutableDateTime('2025-12-01'));
		$closedPosition->method('getOrderPiecesCount')->willReturn(1);
		$closed = $this->createStub(StockClosedPosition::class);
		$closed->method('getDate')->willReturn($end);
		$closed->method('getTotalCloseAmountInBrokerCurrency')->willReturn(
			new AssetPrice($stockAsset, 50.0, CurrencyEnum::USD),
		);
		$closedPosition->method('getStockClosedPosition')->willReturn($closed);

		$futurePosition = $this->createStub(StockPosition::class);
		$futurePosition->method('getOrderDate')->willReturn(new ImmutableDateTime('2026-02-01'));
		$futurePosition->method('getStockClosedPosition')->willReturn(null);

		$oldPosition = $this->createStub(StockPosition::class);
		$oldPosition->method('getOrderDate')->willReturn(new ImmutableDateTime('2025-01-01'));
		$oldClosed = $this->createStub(StockClosedPosition::class);
		$oldClosed->method('getDate')->willReturn(new ImmutableDateTime('2025-12-31'));
		$oldPosition->method('getStockClosedPosition')->willReturn($oldClosed);

		$stockAsset->method('getPositions')->willReturn([
			$openPosition,
			$closedPosition,
			$futurePosition,
			$oldPosition,
		]);
		$stockAssetRepository = $this->createStub(StockAssetRepository::class);
		$stockAssetRepository->method('findAll')->willReturn([$stockAsset]);

		$startPriceRecord = new StockAssetPriceRecord(
			$start,
			CurrencyEnum::USD,
			10.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$start,
		);
		$endPriceRecord = new StockAssetPriceRecord(
			$end,
			CurrencyEnum::USD,
			20.0,
			$stockAsset,
			StockAssetPriceDownloaderEnum::TWELVE_DATA,
			$end,
		);
		$priceRecordRepository = $this->createStub(StockAssetPriceRecordRepository::class);
		$priceRecordRepository->method('findFirstInPeriod')->willReturn($startPriceRecord);
		$priceRecordRepository->method('findLastInPeriod')->willReturn($endPriceRecord);

		$dividend = new StockAssetDividend(
			$stockAsset,
			new ImmutableDateTime('2026-01-15'),
			null,
			null,
			CurrencyEnum::USD,
			1.0,
			new ImmutableDateTime('2026-01-15'),
		);
		$dividendRecord = new StockAssetDividendRecord(
			$dividend,
			10,
			10.0,
			CurrencyEnum::USD,
			null,
			null,
			new ImmutableDateTime('2026-01-15'),
		);
		$dividendRecordRepository = $this->createStub(StockAssetDividendRecordRepository::class);
		$dividendRecordRepository->method('findCashReceivedBetweenDates')->willReturn([$dividendRecord]);

		$currencyConversionFacade = $this->createMock(CurrencyConversionFacade::class);
		$currencyConversionFacade->expects(self::exactly(2))
			->method('getConvertedSummaryPrice')
			->willReturnCallback(static fn (SummaryPrice $price): SummaryPrice => new SummaryPrice(
				CurrencyEnum::CZK,
				$price->getPrice() * 2,
				$price->getCounter(),
			));
		$currencyConversionFacade->expects(self::exactly(2))
			->method('getConvertedAssetPrice')
			->willReturnCallback(static fn (AssetPrice $price): AssetPrice => new AssetPrice(
				$price->getAsset(),
				$price->getPrice() * 2,
				CurrencyEnum::CZK,
			));
		$currencyConversionFacade->expects(self::exactly(2))
			->method('convertSimpleValue')
			->willReturnCallback(static fn (float $value): float => $value * 2);

		$result = $this->createBuilder(
			$recordRepository,
			$dividendRecordRepository,
			currencyConversionFacade: $currencyConversionFacade,
			stockAssetRepository: $stockAssetRepository,
			stockAssetPriceRecordRepository: $priceRecordRepository,
		)->build($this->createReport());

		self::assertSame(1, $result->dividendSection->count);
		self::assertSame(20.0, $result->dividendSection->grossTotalCzk);
		self::assertSame(16.0, $result->dividendSection->netTotalCzk);
		self::assertSame(4.0, $result->dividendSection->taxTotalCzk);
		self::assertFalse($result->dividendSection->partial);
		self::assertNull($result->dividendSection->dividends[0]->paymentDate);
		self::assertSame(16.0, $result->chartSection->dividendsByCompany[0]->value);

		self::assertCount(1, $result->assetSection->assets);
		$asset = $result->assetSection->assets[0];
		self::assertSame('stock', $asset->assetType);
		self::assertSame('ACME', $asset->ticker);
		self::assertSame(10.0, $asset->priceAtStart);
		self::assertSame(20.0, $asset->priceAtEnd);
		self::assertSame(100.0, $asset->marketPerformancePercentage);
		self::assertSame(60.0, $asset->valueAtStartCzk);
		self::assertSame(80.0, $asset->valueAtEndCzk);
		self::assertSame(200.0, $asset->purchasesCzk);
		self::assertSame(100.0, $asset->salesCzk);
		self::assertSame(-80.0, $asset->capitalResultCzk);
		self::assertSame(16.0, $asset->netDividendsCzk);
		self::assertSame(-64.0, $asset->totalContributionCzk);
		self::assertSame([], $asset->warnings);
	}

	public function testBuildReportsMissingCryptoPricesAndCurrencyConversions(): void
	{
		$start = new ImmutableDateTime('2026-01-02 10:00:00');
		$end = new ImmutableDateTime('2026-01-30 20:00:00');
		$recordRepository = $this->createRecordRepository($start, $end);
		$cryptoAsset = $this->createStub(CryptoAsset::class);
		$cryptoAsset->method('getId')->willReturn(Uuid::uuid4());
		$cryptoAsset->method('getName')->willReturn('Bitcoin');
		$cryptoAsset->method('getTicker')->willReturn('BTC');
		$cryptoAsset->method('getCurrency')->willReturn(CurrencyEnum::USD);
		$position = $this->createStub(CryptoPosition::class);
		$position->method('getOrderDate')->willReturn($start);
		$position->method('getOrderPiecesCount')->willReturn(0.5);
		$position->method('getCryptoClosedPosition')->willReturn(null);
		$position->method('getTotalInvestedAmountInBrokerCurrency')->willReturn(
			new AssetPrice($cryptoAsset, 100.0, CurrencyEnum::USD),
		);
		$cryptoAsset->method('getPositions')->willReturn([$position]);
		$cryptoAssetRepository = $this->createStub(CryptoAssetRepository::class);
		$cryptoAssetRepository->method('findAll')->willReturn([$cryptoAsset]);

		$endPriceRecord = new CryptoAssetPriceRecord(
			$end,
			CurrencyEnum::USD,
			20.0,
			$cryptoAsset,
			$end,
		);
		$priceRecordRepository = $this->createStub(CryptoAssetPriceRecordRepository::class);
		$priceRecordRepository->method('findFirstInPeriod')->willReturn(null);
		$priceRecordRepository->method('findLastInPeriod')->willReturn($endPriceRecord);
		$currencyConversionFacade = $this->createStub(CurrencyConversionFacade::class);
		$currencyConversionFacade->method('getConvertedAssetPrice')->willThrowException(
			new MissingCurrencyPairException(),
		);
		$currencyConversionFacade->method('convertSimpleValue')->willThrowException(
			new MissingCurrencyPairException(),
		);

		$result = $this->createBuilder(
			$recordRepository,
			currencyConversionFacade: $currencyConversionFacade,
			cryptoAssetRepository: $cryptoAssetRepository,
			cryptoAssetPriceRecordRepository: $priceRecordRepository,
		)->build($this->createReport());

		self::assertCount(1, $result->assetSection->assets);
		$asset = $result->assetSection->assets[0];
		self::assertSame('crypto', $asset->assetType);
		self::assertNull($asset->valueAtStartCzk);
		self::assertNull($asset->valueAtEndCzk);
		self::assertNull($asset->capitalResultCzk);
		self::assertNull($asset->marketPerformancePercentage);
		self::assertContains('Chybí historický měnový kurz pro datum 2026-01-02.', $asset->warnings);
		self::assertContains('Chybí cena pro výpočet historické hodnoty pozice.', $asset->warnings);
		self::assertContains('Chybí historický měnový kurz pro datum 2026-01-30.', $asset->warnings);
		self::assertContains(
			'Pro výpočet tržního výkonu nejsou k dispozici dvě rozdílná cenová data.',
			$asset->warnings,
		);
	}

	public function testBuildCalculatesPortuPerformanceFromHistoricalPositionValues(): void
	{
		$start = new ImmutableDateTime('2026-01-02 10:00:00');
		$end = new ImmutableDateTime('2026-01-30 20:00:00');
		$recordRepository = $this->createRecordRepository($start, $end);
		$portuAsset = $this->createStub(PortuAsset::class);
		$portuAsset->method('getId')->willReturn(Uuid::uuid4());
		$portuAsset->method('getName')->willReturn('Portu strategy');
		$portuAsset->method('getCurrency')->willReturn(CurrencyEnum::CZK);
		$position = $this->createStub(PortuPosition::class);
		$position->method('getOrderDate')->willReturn(new ImmutableDateTime('2025-12-01'));
		$futurePosition = $this->createStub(PortuPosition::class);
		$futurePosition->method('getOrderDate')->willReturn(new ImmutableDateTime('2026-02-01'));
		$portuAsset->method('getPositions')->willReturn([$position, $futurePosition]);
		$portuAssetRepository = $this->createStub(PortuAssetRepository::class);
		$portuAssetRepository->method('findAll')->willReturn([$portuAsset]);

		$startRecord = $this->createStub(PortuAssetPriceRecord::class);
		$startRecord->method('getDate')->willReturn($start);
		$startRecord->method('getCurrentValueAssetPrice')->willReturn(
			new AssetPrice($portuAsset, 100.0, CurrencyEnum::CZK),
		);
		$startRecord->method('getTotalInvestedAmountAssetPrice')->willReturn(
			new AssetPrice($portuAsset, 80.0, CurrencyEnum::CZK),
		);
		$endRecord = $this->createStub(PortuAssetPriceRecord::class);
		$endRecord->method('getDate')->willReturn($end);
		$endRecord->method('getCurrentValueAssetPrice')->willReturn(
			new AssetPrice($portuAsset, 150.0, CurrencyEnum::CZK),
		);
		$endRecord->method('getTotalInvestedAmountAssetPrice')->willReturn(
			new AssetPrice($portuAsset, 100.0, CurrencyEnum::CZK),
		);
		$priceRecordRepository = $this->createMock(PortuAssetPriceRecordRepository::class);
		$priceRecordRepository->expects(self::once())
			->method('findFirstInPeriod')
			->with($position, $start, $end)
			->willReturn($startRecord);
		$priceRecordRepository->expects(self::once())
			->method('findLastInPeriod')
			->with($position, $start, $end)
			->willReturn($endRecord);
		$currencyConversionFacade = $this->createMock(CurrencyConversionFacade::class);
		$currencyConversionFacade->expects(self::never())->method('convertSimpleValue');

		$result = $this->createBuilder(
			$recordRepository,
			currencyConversionFacade: $currencyConversionFacade,
			portuAssetRepository: $portuAssetRepository,
			portuAssetPriceRecordRepository: $priceRecordRepository,
		)->build($this->createReport());

		self::assertCount(1, $result->assetSection->assets);
		$asset = $result->assetSection->assets[0];
		self::assertSame('portu', $asset->assetType);
		self::assertSame(100.0, $asset->priceAtStart);
		self::assertSame(150.0, $asset->priceAtEnd);
		self::assertSame(25.0, $asset->marketPerformancePercentage);
		self::assertSame(100.0, $asset->valueAtStartCzk);
		self::assertSame(150.0, $asset->valueAtEndCzk);
		self::assertSame(20.0, $asset->purchasesCzk);
		self::assertSame(0.0, $asset->salesCzk);
		self::assertSame(30.0, $asset->capitalResultCzk);
		self::assertSame(30.0, $asset->totalContributionCzk);
	}

	public function testBuildMarksDividendSectionPartialWhenHistoricalRateIsMissing(): void
	{
		$start = new ImmutableDateTime('2026-01-02 10:00:00');
		$end = new ImmutableDateTime('2026-01-30 20:00:00');
		$recordRepository = $this->createRecordRepository($start, $end);
		$stockAsset = $this->createStub(StockAsset::class);
		$stockAsset->method('getId')->willReturn(Uuid::uuid4());
		$stockAsset->method('getName')->willReturn('Acme');
		$stockAsset->method('getTicker')->willReturn('ACME');
		$stockAsset->method('getDividendTax')->willReturn(null);
		$dividendDate = new ImmutableDateTime('2026-01-15');
		$dividend = new StockAssetDividend(
			$stockAsset,
			$dividendDate,
			$dividendDate,
			null,
			CurrencyEnum::USD,
			1.0,
			$dividendDate,
		);
		$record = new StockAssetDividendRecord(
			$dividend,
			10,
			10.0,
			CurrencyEnum::USD,
			null,
			null,
			$dividendDate,
		);
		$dividendRecordRepository = $this->createStub(StockAssetDividendRecordRepository::class);
		$dividendRecordRepository->method('findCashReceivedBetweenDates')->willReturn([$record]);
		$currencyConversionFacade = $this->createStub(CurrencyConversionFacade::class);
		$currencyConversionFacade->method('getConvertedSummaryPrice')->willThrowException(
			new MissingCurrencyPairException(),
		);

		$result = $this->createBuilder(
			$recordRepository,
			$dividendRecordRepository,
			currencyConversionFacade: $currencyConversionFacade,
		)->build($this->createReport());

		self::assertTrue($result->dividendSection->partial);
		self::assertSame(0.0, $result->dividendSection->grossTotalCzk);
		self::assertSame(0.0, $result->dividendSection->netTotalCzk);
		self::assertNull($result->dividendSection->dividends[0]->grossAmountCzk);
		self::assertNull($result->dividendSection->dividends[0]->netAmountCzk);
		self::assertSame(
			['Chybí historický měnový kurz pro přepočet dividendy.'],
			$result->dividendSection->dividends[0]->warnings,
		);
		self::assertContains(
			'Dividendu 2026-01-15 z ACME nebylo možné převést do CZK.',
			$result->summary->warnings,
		);
	}

	private function createBuilder(
		PortfolioStatisticRecordRepository $recordRepository,
		StockAssetDividendRecordRepository|null $dividendRecordRepository = null,
		PortfolioPerformanceProvider|null $performanceProvider = null,
		CurrencyConversionFacade|null $currencyConversionFacade = null,
		StockAssetRepository|null $stockAssetRepository = null,
		CryptoAssetRepository|null $cryptoAssetRepository = null,
		PortuAssetRepository|null $portuAssetRepository = null,
		StockAssetPriceRecordRepository|null $stockAssetPriceRecordRepository = null,
		CryptoAssetPriceRecordRepository|null $cryptoAssetPriceRecordRepository = null,
		PortuAssetPriceRecordRepository|null $portuAssetPriceRecordRepository = null,
	): PortfolioPeriodStatisticBuilder
	{
		if ($performanceProvider === null) {
			$performanceProvider = $this->createStub(PortfolioPerformanceProvider::class);
			$performanceProvider->method('getIncomeBetween')->willReturn(
				new PortfolioPerformanceIncome(0.0, 0.0),
			);
			$performanceProvider->method('getSummaryBetween')->willReturn(
				new PortfolioPerformanceSummary(
					new ImmutableDateTime('2026-01-02 10:00:00'),
					new ImmutableDateTime('2026-01-30 20:00:00'),
					10.0,
					null,
					10.0,
					10.0,
				),
			);
		}

		return new PortfolioPeriodStatisticBuilder(
			$recordRepository,
			$dividendRecordRepository ?? $this->createStub(StockAssetDividendRecordRepository::class),
			$performanceProvider,
			$currencyConversionFacade ?? $this->createStub(CurrencyConversionFacade::class),
			$stockAssetRepository ?? $this->createStub(StockAssetRepository::class),
			$cryptoAssetRepository ?? $this->createStub(CryptoAssetRepository::class),
			$portuAssetRepository ?? $this->createStub(PortuAssetRepository::class),
			$stockAssetPriceRecordRepository ?? $this->createStub(StockAssetPriceRecordRepository::class),
			$cryptoAssetPriceRecordRepository ?? $this->createStub(CryptoAssetPriceRecordRepository::class),
			$portuAssetPriceRecordRepository ?? $this->createStub(PortuAssetPriceRecordRepository::class),
		);
	}

	private function createRecordRepository(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortfolioStatisticRecordRepository
	{
		$repository = $this->createStub(PortfolioStatisticRecordRepository::class);
		$repository->method('findFirstBetweenDates')->willReturn($this->createRecord($start, 100.0, 120.0));
		$repository->method('findLastBetweenDates')->willReturn($this->createRecord($end, 150.0, 190.0));
		$repository->method('findDailyChartValuesBetweenDates')->willReturn([]);

		return $repository;
	}

	private function createRecord(
		ImmutableDateTime $now,
		float $invested,
		float $value,
	): PortfolioStatisticRecord
	{
		$record = new PortfolioStatisticRecord($now);
		$this->addStatistic($record, $now, PortolioStatisticType::TOTAL_INVESTED_IN_CZK, $invested);
		$this->addStatistic($record, $now, PortolioStatisticType::TOTAL_VALUE_IN_CZK, $value);

		return $record;
	}

	private function addStatistic(
		PortfolioStatisticRecord $record,
		ImmutableDateTime $now,
		PortolioStatisticType $type,
		float $value,
	): void
	{
		$record->getPortfolioStatistics()->add(new PortfolioStatistic(
			$record,
			$now,
			DashboardValueGroupEnum::TOTAL_VALUES,
			$type->format(),
			(string) $value,
			'blue',
			null,
			null,
			$type,
			PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
			null,
		));
	}

	private function createReport(): PortfolioPeriodStatistic
	{
		return new PortfolioPeriodStatistic(
			new ImmutableDateTime('2026-01-01 00:00:00'),
			new ImmutableDateTime('2026-01-31 23:59:59'),
			new ImmutableDateTime('2026-02-01 12:00:00'),
		);
	}

}
