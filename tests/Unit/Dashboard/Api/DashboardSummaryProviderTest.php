<?php

declare(strict_types = 1);

namespace App\Test\Unit\Dashboard\Api;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\PriceDiff;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Position\CryptoPositionFacade;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Dashboard\Api\DashboardSummaryProvider;
use App\Dashboard\DashboardStockPerformanceProvider;
use App\Portu\Position\PortuPositionFacade;
use App\Statistic\Total\PortfolioStatisticTotalValue;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use App\Stock\Dividend\Forecast\StockAssetDividendRemainingForecastProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendYearSummaryDTO;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Position\StockPositionRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class DashboardSummaryProviderTest extends TestCase
{

	public function testPreservesNumericPrecisionAndOwnerAcrossAllSummarySources(): void
	{
		$owner = $this->createStub(AppAdmin::class);
		$now = new ImmutableDateTime('2026-10-18 10:30:00+02:00');
		$clock = $this->createStub(DatetimeFactory::class);
		$clock->method('createNow')->willReturn($now);
		$totals = $this->createMock(AssetPriceSummaryFacade::class);
		$totals->expects(self::once())->method('getTotalInvestedAmount')->with(CurrencyEnum::CZK, $owner)
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 300.75));
		$totals->expects(self::once())->method('getCurrentValue')->with(CurrencyEnum::CZK, $owner)
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 450.375));
		$stocks = $this->createMock(StockPositionFacade::class);
		$crypto = $this->createMock(CryptoPositionFacade::class);
		$portu = $this->createMock(PortuPositionFacade::class);
		foreach ([$stocks, $crypto, $portu] as $facade) {
			$facade->expects(self::once())->method('getTotalInvestedAmountSummaryPrice')->with(
				CurrencyEnum::CZK,
				$owner,
			)
				->willReturn(new SummaryPrice(CurrencyEnum::CZK, 100.25));
			$facade->expects(self::once())->method('getCurrentPortfolioValueSummaryPrice')->with(
				CurrencyEnum::CZK,
				$owner,
			)
				->willReturn(new SummaryPrice(CurrencyEnum::CZK, 150.125));
		}

		$positions = $this->createMock(StockPositionRepository::class);
		$positions->expects(self::exactly(6))->method('findAllOpenedInCurrencyForAppAdmin')
			->with(self::isInstanceOf(CurrencyEnum::class), $owner)->willReturn([]);
		$closed = $this->createMock(StockClosedPositionFacade::class);
		$closed->expects(self::once())->method('getAllStockClosedPositionsSummaryPrice')->with($owner)
			->willReturn(new PriceDiff(123.456, 110, CurrencyEnum::CZK));
		$dividends = $this->createMock(StockAssetDividendRecordFacade::class);
		$dividends->expects(self::once())->method('getDividendsByYears')->with($owner)->willReturn([
			2026 => new StockAssetDividendYearSummaryDTO(
				2026,
				new SummaryPrice(CurrencyEnum::CZK, 85.125),
				new SummaryPrice(CurrencyEnum::CZK, 100.25),
			),
		]);
		$forecasts = $this->createMock(StockAssetDividendRemainingForecastProvider::class);
		$forecasts->expects(self::once())->method('getForYear')->with(2026, $owner)->willReturn(
			new StockAssetDividendYearSummaryDTO(
				2026,
				new SummaryPrice(CurrencyEnum::CZK),
				new SummaryPrice(CurrencyEnum::CZK),
			),
		);
		$performance = $this->createMock(PortfolioStatisticTotalValueProvider::class);
		$performance->expects(self::once())->method('getAllTimeValue')->with($owner)->willReturn(
			new PortfolioStatisticTotalValue(
				null,
				'History',
				1000,
				1000,
				1000,
				1125,
				startDate: new ImmutableDateTime('2026-01-01'),
				endDate: new ImmutableDateTime('2026-02-01'),
			),
		);
		$movers = $this->createMock(DashboardStockPerformanceProvider::class);
		$movers->expects(self::once())->method('getDailyPerformance')->with($owner)->willReturn([]);

		$provider = new DashboardSummaryProvider(
			$totals,
			new SummaryPriceService($this->createStub(CurrencyConversionFacade::class)),
			$stocks,
			$positions,
			$closed,
			$crypto,
			$portu,
			$dividends,
			$forecasts,
			$performance,
			$movers,
			$clock,
		);
		$data = $provider->getSummary($owner);
		self::assertSame('2026-10-18T10:30:00+02:00', $data['generatedAt']);
		self::assertSame(300.75, $data['total']['investedAmount']);
		self::assertSame(450.375, $data['total']['currentValue']);
		self::assertSame(149.625, $data['total']['profit']);
		self::assertEqualsWithDelta(49.75062344, $data['total']['profitPercent'], 0.000001);
		self::assertSame(150.125, $data['crypto']['currentValue']);
		self::assertSame(150.125, $data['portu']['currentValue']);
		self::assertSame(123.456, $data['stocks']['closedPositionsProfitIncludingDividends']);
		self::assertSame(
			[['year' => 2026, 'grossAmount' => 100.25, 'netAmount' => 85.125]],
			$data['dividends']['byYear'],
		);
		self::assertSame(
			['year' => 2026, 'grossAmount' => 0.0, 'netAmount' => 0.0],
			$data['dividends']['remainingForecast'],
		);
		self::assertSame('2026-01-01', $data['performance']['from']);
		self::assertSame('2026-02-01', $data['performance']['to']);
		self::assertSame(12.5, $data['performance']['timeWeightedReturnPercent']);
		self::assertSame(12.5, $data['performance']['moneyWeightedReturnPercent']);
		self::assertNull($data['performance']['annualizedTimeWeightedReturnPercent']);
		self::assertNull($data['performance']['xirrPercent']);
	}

}
