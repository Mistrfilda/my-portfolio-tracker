<?php

declare(strict_types = 1);

namespace App\Statistic\UI;

use App\Statistic\PortolioStatisticType;
use App\Statistic\UI\Chart\PortfolioTotalValueChartProvider;
use App\Statistic\UI\Chart\PortfolioTotalValueLastMonthChartProvider;
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
		private PortfolioTotalValueChartProvider $portfolioTotalValueChartProvider,
		private PortfolioTotalValueLastMonthChartProvider $portfolioTotalValueLastMonthChartProvider,
		private ChartControlFactory $chartControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Statistiky a grafy';
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

		return $this->chartControlFactory->create(ChartType::BAR, $provider);
	}

	protected function createComponentPortfolioTotalValueChart(): ChartControl
	{
		$provider = clone $this->portfolioTotalValueChartProvider;
		$provider->addType(PortolioStatisticType::TOTAL_VALUE_IN_CZK);
		$provider->addType(PortolioStatisticType::TOTAL_INVESTED_IN_CZK);

		return $this->chartControlFactory->create(ChartType::LINE, $provider);
	}

	protected function createComponentPortfolioTotalInvestedChart(): ChartControl
	{
		$provider = clone $this->portfolioTotalValueChartProvider;
		$provider->addType(PortolioStatisticType::TOTAL_INVESTED_IN_CZK);

		return $this->chartControlFactory->create(ChartType::LINE, $provider);
	}

	protected function createComponentPortfolioTotalProfitChart(): ChartControl
	{
		$provider = clone $this->portfolioTotalValueChartProvider;
		$provider->addType(PortolioStatisticType::TOTAL_PROFIT);

		return $this->chartControlFactory->create(ChartType::LINE, $provider);
	}

	protected function createComponentPortfolioTotalProfitPercentageChart(): ChartControl
	{
		$provider = clone $this->portfolioTotalValueChartProvider;
		$provider->addType(PortolioStatisticType::TOTAL_PROFIT_PERCENTAGE);

		return $this->chartControlFactory->create(ChartType::LINE, $provider);
	}

	protected function createComponentPortfolioTotalValueLastMonthChart(): ChartControl
	{
		$provider = clone $this->portfolioTotalValueLastMonthChartProvider;
		$provider->addType(PortolioStatisticType::TOTAL_VALUE_IN_CZK);
		$provider->addType(PortolioStatisticType::TOTAL_INVESTED_IN_CZK);
		return $this->chartControlFactory->create(ChartType::LINE, $provider);
	}

}
