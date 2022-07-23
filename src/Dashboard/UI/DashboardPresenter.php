<?php

declare(strict_types = 1);

namespace App\Dashboard\UI;

use App\Dashboard\DashboardValueBuilder;
use App\UI\Base\BaseAdminPresenter;

class DashboardPresenter extends BaseAdminPresenter
{

	public function __construct(
		private DashboardValueBuilder $dashboardValueBuilder,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->dashboardValues = $this->dashboardValueBuilder->buildValues();
		$this->template->heading = 'Dashboard';
	}

}
