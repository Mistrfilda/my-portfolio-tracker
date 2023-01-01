<?php

declare(strict_types = 1);

namespace App\Dashboard\UI\DashboardValueControl;

use App\Dashboard\DashboardValueGroup;
use App\UI\Base\BaseControlTemplate;

class DashboardValueControlTemplate extends BaseControlTemplate
{

	/** @var array<int, DashboardValueGroup> */
	public array $dashboardValueGroups;

}
