<?php

declare(strict_types = 1);

namespace App\Goal\UI\Control;

use App\Goal\PortfolioGoal;
use App\UI\Base\BaseControlTemplate;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

class PortfolioGoalControlTemplate extends BaseControlTemplate
{

	/** @var array<PortfolioGoal> */
	public array $goals;

	public ImmutableDateTime $now;

}
