<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use App\UI\Base\BaseControlTemplate;
use Nette\SmartObject;

class FrontChartControlTemplate extends BaseControlTemplate
{

	use SmartObject;

	public string $chartId;

	public string $chartType;

}
