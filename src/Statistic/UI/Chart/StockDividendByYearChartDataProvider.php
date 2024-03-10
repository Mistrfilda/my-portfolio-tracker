<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;

class StockDividendByYearChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{

	}

	public function getChartData(): ChartData
	{
		$records = $this->stockAssetDividendRecordRepository->findAllForMonthChart();
		/** @var array<string|int, SummaryPrice> $preparedData */
		$preparedData = [];

		foreach ($records as $record) {
			$recordPrice = $record->getSummaryPrice();
			if ($recordPrice->getCurrency() !== CurrencyEnum::CZK) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$record->getStockAssetDividend()->getExDate(),
				);
			}

			$key = $record->getStockAssetDividend()->getExDate()->format('Y');
			if (array_key_exists($key, $preparedData)) {
				$preparedData[$key]->addSummaryPrice($recordPrice);
				continue;
			}

			$preparedData[$key] = $recordPrice;
		}

		$chartData = new ChartData('Dividendy během let', tooltipSuffix: 'Kč');

		foreach ($preparedData as $key => $summaryPrice) {
			$chartData->add((string) $key, (int) $summaryPrice->getPrice());
		}

		return $chartData;
	}

}
