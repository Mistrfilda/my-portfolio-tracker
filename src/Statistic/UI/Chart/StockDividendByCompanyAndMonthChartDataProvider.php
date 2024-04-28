<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;

class StockDividendByCompanyAndMonthChartDataProvider implements ChartDataProvider
{

	private bool $shouldDeductTax = true;

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{
	}

	/**
	 * @param array<mixed> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		//do nothing
	}

	public function notDeductTax(): void
	{
		$this->shouldDeductTax = false;
	}

	public function getChartData(): ChartDataSet
	{
		$records = $this->stockAssetDividendRecordRepository->findAllForMonthChart();

		$labels = [];
		foreach ($records as $record) {
			$key = $record->getStockAssetDividend()->getExDate()->format('Y-m');
			$labels[$key] = $key;
		}

		$values = [];
		foreach ($records as $record) {
			$key = $record->getStockAssetDividend()->getStockAssetId()->toString();
			$recordPrice = $record->getSummaryPrice($this->shouldDeductTax);
			if ($recordPrice->getCurrency() !== CurrencyEnum::CZK) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$values[$key]['name'] = $record->getStockAssetChartLabel();
			$values[$key]['values'][$record->getStockAssetDividend()->getExDate()->format(
				'Y-m',
			)] = (int) $recordPrice->getPrice();
		}

		foreach ($values as $key => $value) {
			$sortedValues = [];
			foreach ($labels as $label) {
				if (array_key_exists($label, $values[$key]['values'])) {
					$sortedValues[$label] = $values[$key]['values'][$label];
				} else {
					$sortedValues[$label] = 0;
				}
			}

			$values[$key]['values'] = $sortedValues;
		}

		/** @var array<ChartData> $chartData */
		$chartData = [];
		foreach ($values as $value) {
			$stockData = new ChartData($value['name']);
			foreach ($value['values'] as $key => $amount) {
				$stockData->add($key, $amount);
			}

			$chartData[] = $stockData;
		}

		return new ChartDataSet($chartData, 'Kč');
	}

	public function getIdForChart(): string
	{
		return md5(self::class) . md5($this->shouldDeductTax ? '1' : '-1');
	}

}
