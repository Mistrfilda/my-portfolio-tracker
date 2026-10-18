<?php

declare(strict_types = 1);

namespace App\Dashboard\Api;

use App\Admin\AppAdmin;
use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPrice;
use App\Asset\Price\SummaryPriceService;
use App\Crypto\Position\CryptoPositionFacade;
use App\Currency\CurrencyEnum;
use App\Dashboard\DashboardStockPerformanceProvider;
use App\Portu\Position\PortuPositionFacade;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Forecast\StockAssetDividendRemainingForecastProvider;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\Stock\Dividend\Record\StockAssetDividendYearSummaryDTO;
use App\Stock\Position\Closed\StockClosedPositionFacade;
use App\Stock\Position\StockPositionFacade;
use App\Stock\Position\StockPositionRepository;
use Mistrfilda\Datetime\DatetimeFactory;
use const DATE_ATOM;

class DashboardSummaryProvider
{

	public function __construct(
		private AssetPriceSummaryFacade $totals,
		private SummaryPriceService $prices,
		private StockPositionFacade $stocks,
		private StockPositionRepository $stockPositions,
		private StockClosedPositionFacade $closedStocks,
		private CryptoPositionFacade $crypto,
		private PortuPositionFacade $portu,
		private StockAssetDividendRecordFacade $dividends,
		private StockAssetDividendRemainingForecastProvider $forecasts,
		private PortfolioStatisticTotalValueProvider $performance,
		private DashboardStockPerformanceProvider $stockPerformance,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	/** @return array<string, mixed> */
	public function getSummary(AppAdmin $appAdmin): array
	{
		$now = $this->datetimeFactory->createNow();
		$currency = CurrencyEnum::CZK;
		$performance = $this->performance->getAllTimeValue($appAdmin);
		$forecast = $this->forecasts->getForYear($now->getYear(), $appAdmin);
		$dailyPerformance = $this->stockPerformance->getDailyPerformance($appAdmin);
		$byCurrency = [];
		foreach (CurrencyEnum::cases() as $tradingCurrency) {
			$byCurrency[] = [
				'currency' => $tradingCurrency->value,
				'currentValue' => $this->prices->getSummaryPriceForPositions(
					$currency,
					$this->stockPositions->findAllOpenedInCurrencyForAppAdmin($tradingCurrency, $appAdmin),
				)->getPrice(),
			];
		}

		return [
			'generatedAt' => $now->format(DATE_ATOM),
			'currency' => $currency->value,
			'total' => $this->amounts(
				$this->totals->getTotalInvestedAmount($currency, $appAdmin),
				$this->totals->getCurrentValue($currency, $appAdmin),
			),
			'stocks' => [
				'amounts' => $this->amounts(
					$this->stocks->getTotalInvestedAmountSummaryPrice($currency, $appAdmin),
					$this->stocks->getCurrentPortfolioValueSummaryPrice($currency, $appAdmin),
				),
				'closedPositionsProfitIncludingDividends' => $this->closedStocks
					->getAllStockClosedPositionsSummaryPrice($appAdmin)->getPriceDifference(),
				'byTradingCurrency' => $byCurrency,
			],
			'crypto' => $this->amounts(
				$this->crypto->getTotalInvestedAmountSummaryPrice($currency, $appAdmin),
				$this->crypto->getCurrentPortfolioValueSummaryPrice($currency, $appAdmin),
			),
			'portu' => $this->amounts(
				$this->portu->getTotalInvestedAmountSummaryPrice($currency, $appAdmin),
				$this->portu->getCurrentPortfolioValueSummaryPrice($currency, $appAdmin),
			),
			'dividends' => [
				'byYear' => array_values(array_map(
					$this->dividendAmounts(...),
					$this->dividends->getDividendsByYears($appAdmin),
				)),
				'remainingForecast' => $forecast === null ? null : $this->dividendAmounts($forecast),
			],
			'performance' => $performance === null ? null : [
				'from' => $performance->getStartDate()?->format('Y-m-d'),
				'to' => $performance->getEndDate()?->format('Y-m-d'),
				'timeWeightedReturnPercent' => $performance->getTimeWeightedReturn(),
				'annualizedTimeWeightedReturnPercent' => $performance->getAnnualizedTwr(),
				'moneyWeightedReturnPercent' => $performance->getMoneyWeightedReturn(),
				'xirrPercent' => $performance->getXirr(),
			],
			'stockMovers' => [
				'best' => array_map($this->stockMover(...), array_slice($dailyPerformance, 0, 4)),
				'worst' => array_map($this->stockMover(...), array_slice(array_reverse($dailyPerformance), 0, 4)),
			],
		];
	}

	/** @return array{investedAmount: float, currentValue: float, profit: float, profitPercent: float} */
	private function amounts(SummaryPrice $invested, SummaryPrice $current): array
	{
		$diff = $this->prices->getSummaryPriceDiff($current, $invested);
		return [
			'investedAmount' => $invested->getPrice(),
			'currentValue' => $current->getPrice(),
			'profit' => $diff->getPriceDifference(),
			'profitPercent' => $diff->getPercentageDifference(),
		];
	}

	/** @return array{year: int, grossAmount: float, netAmount: float} */
	private function dividendAmounts(StockAssetDividendYearSummaryDTO $summary): array
	{
		return [
			'year' => $summary->getYear(),
			'grossAmount' => $summary->getSummaryPriceWithTax()->getPrice(),
			'netAmount' => $summary->getSummaryPriceWithoutTax()->getPrice(),
		];
	}

	/**
	 * @param array{stockAsset: StockAsset, trend: float} $performance
	 * @return array<string, mixed>
	 */
	private function stockMover(array $performance): array
	{
		$stock = $performance['stockAsset'];
		return [
			'stock' => [
				'id' => $stock->getId()->toString(),
				'name' => $stock->getName(),
				'ticker' => $stock->getTicker(),
			],
			'changePercent' => $performance['trend'],
		];
	}

}
