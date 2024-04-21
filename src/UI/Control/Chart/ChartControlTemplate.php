<?php

declare(strict_types = 1);

namespace App\UI\Control\Chart;

use App\UI\Base\BaseControlTemplate;
use Nette\SmartObject;

class ChartControlTemplate extends BaseControlTemplate
{

	use SmartObject;

	public string $chartId;

	public string $chartType;

	public int $shouldUpdateOnAjaxRequest;

}
