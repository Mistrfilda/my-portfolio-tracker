<?php

declare(strict_types = 1);

namespace App\Dashboard\UI;

use App\Dashboard\DashboardValueBuilderFacade;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControl;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControlFactory;
use App\System\UI\SystemValueControl;
use App\System\UI\SystemValueControlFactory;
use App\UI\Base\BaseAdminPresenter;

class DashboardPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly DashboardValueBuilderFacade $dashboardValueBuilder,
		private readonly DashboardValueControlFactory $dashboardValueControlFactory,
		private readonly SystemValueControlFactory $systemValueControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Dashboard';
	}

	protected function createComponentDashboardValueControl(): DashboardValueControl
	{
		return $this->dashboardValueControlFactory->create($this->dashboardValueBuilder);
	}

	public function createComponentSystemValueControl(): SystemValueControl
	{
		return $this->systemValueControlFactory->create();
	}

}
