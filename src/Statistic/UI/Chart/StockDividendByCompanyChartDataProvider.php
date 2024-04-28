<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;

class StockDividendByCompanyChartDataProvider implements ChartDataProvider
{

	private bool $shouldDeductTax = true;

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	public function getChartData(): ChartDataSet
	{
		/** @var array<string, array{'stockAsset': StockAsset, 'summaryPrice': SummaryPrice}> $recordPrices */
		$recordPrices = [];
		$records = $this->stockAssetDividendRecordRepository->findAll();
		foreach ($records as $record) {
			$key = $record->getStockAssetDividend()->getStockAsset()->getId()->toString();
			if (array_key_exists($key, $recordPrices) === false) {
				$recordPrices[$key] = [
					'stockAsset' => $record->getStockAssetDividend()->getStockAsset(),
					'summaryPrice' => new SummaryPrice(CurrencyEnum::CZK),
				];
			}

			$recordPrice = $record->getSummaryPrice($this->shouldDeductTax);
			if ($recordPrice->getCurrency() !== CurrencyEnum::CZK) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$recordPrices[$key]['summaryPrice']->addSummaryPrice($recordPrice);
		}

		usort(
			$recordPrices,
			static fn ($item1, $item2): int => $item2['summaryPrice']->getPrice() <=> $item1['summaryPrice']->getPrice(),
		);

		$chartData = [];
		/** @var array{'stockAsset': StockAsset, 'summaryPrice': SummaryPrice} $recordPrice */
		foreach ($recordPrices as $recordPrice) {
			$recordChartData = new ChartData($recordPrice['stockAsset']->getName());
			$recordChartData->add(
				$recordPrice['stockAsset']->getName(),
				(int) $recordPrice['summaryPrice']->getPrice(),
			);
			$chartData[] = $recordChartData;
		}

		return new ChartDataSet($chartData, 'Kč', ['Dividenda v CZK']);
	}

	/**
	 * @param array<string, string> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		// not used
	}

	public function getIdForChart(): string
	{
		return md5(self::class) . md5($this->shouldDeductTax ? '1' : '-1');
	}

}
