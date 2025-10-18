<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;

class PortfolioGoalMonthlyIncomeResolver implements PortfolioGoalResolver
{

	public function __construct(
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
	)
	{

	}

	public function resolve(PortfolioGoal $portfolioGoal): float
	{
		$workMonthlyIncome = $this->workMonthlyIncomeRepository->getByYearAndMonth(
			$portfolioGoal->getStartDate()->getYear(),
			$portfolioGoal->getStartDate()->getMonth(),
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
