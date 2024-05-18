<?php

declare(strict_types = 1);

namespace App\Statistic\UI;

use App\UI\Base\BaseAdminPresenterTemplate;

class PortfolioStatisticTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<PortfolioStatisticChart> */
	public array $charts;

}
