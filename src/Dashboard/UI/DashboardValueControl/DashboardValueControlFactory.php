<?php

declare(strict_types = 1);

namespace App\Dashboard\UI\DashboardValueControl;

use App\Dashboard\DashboardValueBuilder;

interface DashboardValueControlFactory
{

	public function create(DashboardValueBuilder $dashboardValueBuilder): DashboardValueControl;

}
