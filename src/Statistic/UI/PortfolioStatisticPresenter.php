<?php

declare(strict_types = 1);

namespace App\Statistic\UI;

use App\Statistic\UI\Chart\StockDividendByMonthChartDataProvider;
use App\Statistic\UI\Chart\StockDividendByYearChartDataProvider;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;

class PortfolioStatisticPresenter extends BaseAdminPresenter
{
	public function __construct(
		private StockDividendByMonthChartDataProvider $stockDividendByMonthChartDataProvider,
		private StockDividendByYearChartDataProvider $stockDividendByYearChartDataProvider,
		private ChartControlFactory $chartControlFactory,
	)
	{
		parent::__construct();
	}

	protected function createComponentStockDividendsByMonthChart(): ChartControl
	{
		return $this->chartControlFactory->create(
			ChartType::BAR,
			$this->stockDividendByMonthChartDataProvider,
		);
	}

	protected function createComponentStockDividendsByYearChart(): ChartControl
	{
		return $this->chartControlFactory->create(
			ChartType::BAR,
			$this->stockDividendByYearChartDataProvider,
		);
	}

	protected function createComponentStockDividendsByYearWithTaxChart(): ChartControl
	{
		$provider = clone $this->stockDividendByYearChartDataProvider;
		$provider->notDeductTax();

		return $this->chartControlFactory->create(
			ChartType::BAR,
			$provider,
		);
	}

}
