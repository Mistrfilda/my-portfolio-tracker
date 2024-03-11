<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;

class StockDividendByMonthChartDataProvider implements ChartDataProvider
{

	private bool $shouldDeductTax = true;

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{

	}

	public function notDeductTax(): void
	{
		$this->shouldDeductTax = false;
	}

	public function getChartData(): ChartData
	{
		$records = $this->stockAssetDividendRecordRepository->findAllForMonthChart();
		/** @var array<string, SummaryPrice> $preparedData */
		$preparedData = [];

		foreach ($records as $record) {
			$recordPrice = $record->getSummaryPrice($this->shouldDeductTax);
			if ($recordPrice->getCurrency() !== CurrencyEnum::CZK) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$key = $record->getStockAssetDividend()->getExDate()->format('Y-m');
			if (array_key_exists($key, $preparedData)) {
				$preparedData[$key]->addSummaryPrice($recordPrice);
				continue;
			}

			$preparedData[$key] = $recordPrice;
		}

		$chartData = new ChartData('Dividendy během měsíců', tooltipSuffix: 'Kč');

		foreach ($preparedData as $key => $summaryPrice) {
			$chartData->add($key, (int) $summaryPrice->getPrice());
		}

		return $chartData;
	}

}
