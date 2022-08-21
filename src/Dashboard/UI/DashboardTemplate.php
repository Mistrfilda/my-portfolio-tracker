<?php

declare(strict_types = 1);

namespace App\Dashboard\UI;

use App\Dashboard\DashboardValueGroup;
use App\UI\Base\BaseAdminPresenterTemplate;

class DashboardTemplate extends BaseAdminPresenterTemplate
{

	/** @var array<int, DashboardValueGroup> */
	public array $dashboardValueGroups;

}
