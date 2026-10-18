<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;

class PortfolioGoalMonthlyIncomeResolver implements PortfolioGoalResolver
{

	public function __construct(
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private WorkMonthlyIncomeOwnerProvider $ownerProvider,
	)
	{

	}

	public function resolve(PortfolioGoal $portfolioGoal): float
	{
		// Legacy goals use the configured income owner until goals receive their own ownership.
		$workMonthlyIncome = $this->workMonthlyIncomeRepository->getByYearAndMonthForAppAdmin(
			$portfolioGoal->getStartDate()->getYear(),
			$portfolioGoal->getStartDate()->getMonth(),
			$this->ownerProvider->getOwner(),
		);

		if ($workMonthlyIncome === null) {
			return 0;
		}

		return $workMonthlyIncome->getSummaryPrice()->getPrice();
	}

	public function canResolveType(PortfolioGoal $portfolioGoal): bool
	{
		return $portfolioGoal->getType() === PortfolioGoalTypeEnum::MONTHLY_INCOME;
	}

}
