<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Admin\AppAdmin;
use App\Dashboard\DashboardStockPerformanceProvider;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use App\Statistic\Total\PortfolioTotalStatisticControlFacade;

class PortfolioStatisticCacheWarmer
{

	public function __construct(
		private readonly PortfolioStatisticTotalValueProvider $totalValueProvider,
		private readonly PortfolioTotalStatisticControlFacade $totalStatisticFacade,
		private readonly DashboardStockPerformanceProvider $stockPerformanceProvider,
	)
	{
	}

	public function warmUp(AppAdmin $appAdmin): void
	{
		$this->totalValueProvider->getAllTimeValue($appAdmin);
		$this->totalStatisticFacade->getData($appAdmin, onlyYears: true);
		$this->totalStatisticFacade->getData($appAdmin);
		$this->stockPerformanceProvider->getDailyPerformance($appAdmin);
	}

}
