<?php

declare(strict_types = 1);

namespace App\Dashboard\UI\DashboardValueControl;

use App\Dashboard\DashboardValueBuilder;
use App\UI\Base\BaseControl;

class DashboardValueControl extends BaseControl
{

	public function __construct(
		private readonly DashboardValueBuilder $dashboardValueBuilder,
	)
	{
	}

	public function render(): void
	{
		$template = $this->getTemplate();
		$template->dashboardValueGroups = $this->dashboardValueBuilder->buildValues();
		$template->setFile(str_replace('.php', '.latte', __FILE__));
		$template->render();
	}

}
