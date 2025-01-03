<?php

declare(strict_types = 1);

namespace App\Goal\UI\Control;

interface PortfolioGoalControlFactory
{

	public function create(): PortfolioGoalControl;

}
