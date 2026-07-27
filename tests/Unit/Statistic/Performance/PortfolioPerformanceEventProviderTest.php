<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Asset\Asset;
use App\Asset\Position\AssetPosition;
use App\Asset\Price\AssetPrice;
use App\Asset\Price\SummaryPrice;
use App\Crypto\Position\Closed\CryptoClosedPosition;
use App\Crypto\Position\Closed\CryptoClosedPositionRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecord;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\Stock\Dividend\StockAssetDividend;
use App\Stock\Position\Closed\StockClosedPosition;
use App\Stock\Position\Closed\StockClosedPositionRepository;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPerformanceEventProviderTest extends TestCase
{

	public function testIncomeUsesStartExclusiveAndEndInclusiveBoundariesAndCachesEvents(): void
	{
		$start = new ImmutableDateTime('2026-01-01 00:00:00');
		$end = new ImmutableDateTime('2026-01-31 23:59:59');
		$asset = $this->createStub(Asset::class);

		$stockPosition = $this->createStub(AssetPosition::class);
		$stockPosition->method('getTotalInvestedAmountInBrokerCurrency')->willReturn(
			new AssetPrice($asset, 100.0, CurrencyEnum::USD),
		);
		$stockClosedPosition = $this->createStub(StockClosedPosition::class);
		$stockClosedPosition->method('getAssetPositon')->willReturn($stockPosition);
		$stockClosedPosition->method('getTotalCloseAmountInBrokerCurrency')->willReturn(
			new AssetPrice($asset, 150.0, CurrencyEnum::USD),
		);
		$stockClosedPosition->method('getDate')->willReturn($start);

		$cryptoPosition = $this->createStub(AssetPosition::class);
		$cryptoPosition->method('getTotalInvestedAmountInBrokerCurrency')->willReturn(
			new AssetPrice($asset, 40.0, CurrencyEnum::USD),
		);
		$cryptoClosedPosition = $this->createStub(CryptoClosedPosition::class);
		$cryptoClosedPosition->method('getAssetPositon')->willReturn($cryptoPosition);
		$cryptoClosedPosition->method('getTotalCloseAmountInBrokerCurrency')->willReturn(
			new AssetPrice($asset, 100.0, CurrencyEnum::USD),
		);
		$cryptoClosedPosition->method('getDate')->willReturn($end);

		$stockClosedPositionRepository = $this->createMock(StockClosedPositionRepository::class);
		$stockClosedPositionRepository->expects(self::once())
			->method('findAll')
			->willReturn([$stockClosedPosition]);
		$cryptoClosedPositionRepository = $this->createMock(CryptoClosedPositionRepository::class);
		$cryptoClosedPositionRepository->expects(self::once())
			->method('findAll')
			->willReturn([$cryptoClosedPosition]);

		$dividendDate = new ImmutableDateTime('2026-01-15 12:00:00');
		$dividend = $this->createStub(StockAssetDividend::class);
		$dividend->method('getPaymentDate')->willReturn(null);
		$dividend->method('getExDate')->willReturn($dividendDate);
		$dividendRecord = $this->createStub(StockAssetDividendRecord::class);
		$dividendRecord->method('getStockAssetDividend')->willReturn($dividend);
		$dividendRecord->method('getSummaryPriceInBrokerCurrency')->willReturn(
			new SummaryPrice(CurrencyEnum::USD, 12.0, 1),
		);
		$dividendRecordRepository = $this->createMock(StockAssetDividendRecordRepository::class);
		$dividendRecordRepository->expects(self::once())
			->method('findAll')
			->willReturn([$dividendRecord]);

		$currencyConversionFacade = $this->createMock(CurrencyConversionFacade::class);
		$currencyConversionFacade->expects(self::exactly(4))
			->method('getConvertedAssetPrice')
			->willReturnCallback(static fn (
				AssetPrice $price,
				CurrencyEnum $currency,
				ImmutableDateTime $date,
			): AssetPrice => new AssetPrice($price->getAsset(), $price->getPrice(), $currency));
		$currencyConversionFacade->expects(self::once())
			->method('getConvertedSummaryPrice')
			->with(
				self::isInstanceOf(SummaryPrice::class),
				CurrencyEnum::CZK,
				$dividendDate,
			)
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 12.0, 1));

		$provider = new PortfolioPerformanceEventProvider(
			$stockClosedPositionRepository,
			$cryptoClosedPositionRepository,
			$dividendRecordRepository,
			$currencyConversionFacade,
		);

		$income = $provider->getIncomeBetween($start, $end);

		self::assertSame(60.0, $income->realizedProfit);
		self::assertSame(12.0, $income->netDividends);

		$followingIncome = $provider->getIncomeBetween($end, new ImmutableDateTime('2026-02-01 23:59:59'));

		self::assertSame(0.0, $followingIncome->realizedProfit);
		self::assertSame(0.0, $followingIncome->netDividends);
	}

}
