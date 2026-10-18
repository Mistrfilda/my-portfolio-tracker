<?php

declare(strict_types = 1);

namespace App\Dashboard\UI;

use App\Admin\LegacyPortfolioOwnerProvider;
use App\Dashboard\DashboardValueBuilderFacade;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControl;
use App\Dashboard\UI\DashboardValueControl\DashboardValueControlFactory;
use App\Goal\UI\Control\PortfolioGoalControl;
use App\Goal\UI\Control\PortfolioGoalControlFactory;
use App\Statistic\Total\UI\Control\PortfolioStatisticTotalControl;
use App\Statistic\Total\UI\Control\PortfolioStatisticTotalControlFactory;
use App\UI\Base\BaseAdminPresenter;

/**
 * @property-read DashboardTemplate $template
 */
class DashboardPresenter extends BaseAdminPresenter
{

	public function __construct(
		private readonly DashboardValueBuilderFacade $dashboardValueBuilder,
		private readonly LegacyPortfolioOwnerProvider $legacyOwnerProvider,
		private readonly DashboardValueControlFactory $dashboardValueControlFactory,
		private readonly PortfolioGoalControlFactory $portfolioGoalControlFactory,
		private readonly PortfolioStatisticTotalControlFactory $portfolioStatisticTotalControl,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Dashboard';
		$this->template->showLegacyPortfolioData = $this->legacyOwnerProvider->isCurrentUserOwner();
	}

	protected function createComponentDashboardValueControl(): DashboardValueControl
	{
		return $this->dashboardValueControlFactory->create($this->dashboardValueBuilder);
	}

	protected function createComponentPortfolioGoalControl(): PortfolioGoalControl
	{
		if (!$this->legacyOwnerProvider->isCurrentUserOwner()) {
			$this->error('Not found.');
		}

		return $this->portfolioGoalControlFactory->create();
	}

	protected function createComponentPortfolioStatisticTotalControl(): PortfolioStatisticTotalControl
	{
		if (!$this->legacyOwnerProvider->isCurrentUserOwner()) {
			$this->error('Not found.');
		}

		$control = $this->portfolioStatisticTotalControl->create();
		$control->renderOnlyYears();
		return $control;
	}

}
