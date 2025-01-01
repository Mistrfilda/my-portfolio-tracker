<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Asset\Price\SummaryPrice;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;

class PortfolioGoalTotalIncomeResolver implements PortfolioGoalResolver
{

	public function __construct(
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
	)
	{

	}

	public function resolve(PortfolioGoal $portfolioGoal): float
	{
		$incomeRows = $this->workMonthlyIncomeRepository->findAll($portfolioGoal->getStartDate()->getYear());
		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($incomeRows as $incomeRow) {
			$totalSummaryPrice->addSummaryPrice($incomeRow->getSummaryPrice());
		}

		return $totalSummaryPrice->getPrice();
	}

	public function canResolveType(PortfolioGoal $portfolioGoal): bool
	{
		return $portfolioGoal->getType() === PortfolioGoalTypeEnum::TOTAL_INCOME;
	}

}
