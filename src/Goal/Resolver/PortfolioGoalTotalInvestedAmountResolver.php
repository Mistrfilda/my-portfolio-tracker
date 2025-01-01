<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;

class PortfolioGoalTotalInvestedAmountResolver implements PortfolioGoalResolver
{

	public function __construct(
		private AssetPriceSummaryFacade $assetPriceSummaryFacade,
	)
	{

	}

	public function resolve(PortfolioGoal $portfolioGoal): float
	{
		$totalInvestedAmount = $this->assetPriceSummaryFacade->getTotalInvestedAmount(
			CurrencyEnum::CZK,
		);

		return $totalInvestedAmount->getPrice();
	}

	public function canResolveType(PortfolioGoal $portfolioGoal): bool
	{
		return $portfolioGoal->getType() === PortfolioGoalTypeEnum::TOTAL_INVESTED_AMOUNT;
	}

}
