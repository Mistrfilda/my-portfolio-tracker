<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Goal\PortfolioGoal;

interface PortfolioGoalResolver
{

	public function resolve(PortfolioGoal $portfolioGoal): float;

	public function canResolveType(PortfolioGoal $portfolioGoal): bool;

}
