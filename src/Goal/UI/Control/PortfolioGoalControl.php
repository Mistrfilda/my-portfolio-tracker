<?php

declare(strict_types = 1);

namespace App\Goal\UI\Control;

use App\Goal\PortfolioGoalRepository;
use App\UI\Base\BaseControl;
use Mistrfilda\Datetime\DatetimeFactory;

class PortfolioGoalControl extends BaseControl
{

	public function __construct(
		private PortfolioGoalRepository $portfolioGoalRepository,
		private DatetimeFactory $datetimeFactory,
	)
	{

	}

	public function render(): void
	{
		$template = $this->createTemplate(PortfolioGoalControlTemplate::class);
		assert($template instanceof PortfolioGoalControlTemplate);

		$template->goals = $this->portfolioGoalRepository->findActive(
			$this->datetimeFactory->createNow(),
		);
		$template->setFile(__DIR__ . '/PortfolioGoalControl.latte');
		$template->render();
	}

}
