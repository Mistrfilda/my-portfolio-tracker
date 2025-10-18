<?php

declare(strict_types = 1);

namespace App\Statistic\Total\UI\Control;

use App\Statistic\Total\PortfolioTotalStatisticControlFacade;
use App\UI\Base\BaseControl;

class PortfolioStatisticTotalControl extends BaseControl
{

	private bool $renderOnlyYears = false;

	public function __construct(
		private PortfolioTotalStatisticControlFacade $portfolioTotalStatisticControlFacade,
	)
	{
	}

	public function renderOnlyYears(): void
	{
		$this->renderOnlyYears = true;
	}

	public function render(): void
	{
		$this->getTemplate()->renderOnlyYears = $this->renderOnlyYears;
		$this->getTemplate()->groups = array_reverse($this->portfolioTotalStatisticControlFacade->getData());
		$this->getTemplate()->setFile(__DIR__ . '/PortfolioStatisticTotalControl.latte');
		$this->getTemplate()->render();
	}

}
