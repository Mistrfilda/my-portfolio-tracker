<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Chart;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPriceService;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordFacade;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;
use App\UI\Filter\SummaryPriceFilter;

class TotalInvestedAmountPieChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private AssetPriceSummaryFacade $assetPriceSummaryFacade,
		private StockAssetDividendRecordFacade $stockAssetDividendRecordFacade,
		private SummaryPriceService $summaryPriceService,
	)
	{

	}

	public function getChartData(): ChartDataSet
	{
		$totalInvestedAmount = $this->assetPriceSummaryFacade->getTotalInvestedAmount(
			CurrencyEnum::CZK,
		);

		$totalDividends = $this->stockAssetDividendRecordFacade->getTotalSummaryPrice();

		$totalInvestedCash = $this->summaryPriceService->getSummaryPriceDiff(
			$totalInvestedAmount,
			$totalDividends,
		);

		$chartData = new ChartData(
			sprintf('Rozdělení investované částky (celkem %s)', SummaryPriceFilter::format($totalInvestedAmount)),
		);
		$chartData->add('Investovaná hotovost', $totalInvestedCash->getPriceDifference());
		$chartData->add('Reinvestované dividendy', $totalDividends->getPrice());

		return new ChartDataSet([$chartData], tooltipSuffix: 'Kč');
	}

	/**
	 * @param array<mixed> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		//do nothing
	}

	public function getIdForChart(): string
	{
		return md5(self::class);
	}

}
