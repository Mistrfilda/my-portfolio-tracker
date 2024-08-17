<?php

declare(strict_types = 1);

namespace App\Statistic\UI\Total\UI;

use App\Statistic\UI\Total\UI\Control\PortfolioStatisticTotalControl;
use App\Statistic\UI\Total\UI\Control\PortfolioStatisticTotalControlFactory;
use App\UI\Base\BaseAdminPresenter;

class PortfolioStatisticTotalPresenter extends BaseAdminPresenter
{

	public function __construct(
		private PortfolioStatisticTotalControlFactory $portfolioStatisticTotalControl,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Statistiky';
	}

	protected function createComponentPortfolioStatisticTotalControl(): PortfolioStatisticTotalControl
	{
		return $this->portfolioStatisticTotalControl->create();
	}

}
