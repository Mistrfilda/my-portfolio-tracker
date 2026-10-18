<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Asset\Price\SummaryPrice;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;

class PortfolioGoalTotalIncomeResolver implements PortfolioGoalResolver
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
		$incomeRows = $this->workMonthlyIncomeRepository->findAllForAppAdmin(
			$this->ownerProvider->getOwner(),
			$portfolioGoal->getStartDate()->getYear(),
		);
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
