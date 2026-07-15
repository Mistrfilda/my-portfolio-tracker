<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic\UI;

use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\UI\Base\BaseAdminPresenterTemplate;

class PortfolioPeriodStatisticTemplate extends BaseAdminPresenterTemplate
{

	public PortfolioPeriodStatistic|null $report = null;

}
