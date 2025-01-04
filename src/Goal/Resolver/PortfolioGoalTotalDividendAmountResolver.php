<?php

declare(strict_types = 1);

namespace App\Goal\Resolver;

use App\Asset\Price\SummaryPrice;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Goal\PortfolioGoal;
use App\Goal\PortfolioGoalTypeEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;

class PortfolioGoalTotalDividendAmountResolver implements PortfolioGoalResolver
{

	public function __construct(
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
	)
	{

	}

	public function resolve(PortfolioGoal $portfolioGoal): float
	{
		$totalDividendIncome = new SummaryPrice(CurrencyEnum::CZK);

		foreach ($this->stockAssetDividendRecordRepository->findBetweenDates(
			$portfolioGoal->getStartDate(),
			$portfolioGoal->getEndDate(),
		) as $stockAssetDividendRecord) {
			$recordPrice = $stockAssetDividendRecord->getSummaryPrice();
			if ($recordPrice->getCurrency() !== $totalDividendIncome->getCurrency()) {
				$recordPrice = $this->currencyConversionFacade->getConvertedSummaryPrice(
					$recordPrice,
					CurrencyEnum::CZK,
					$stockAssetDividendRecord->getStockAssetDividend()->getExDate(),
				);
			}

			$totalDividendIncome->addSummaryPrice($recordPrice);
		}

		return $totalDividendIncome->getPrice();
	}

	public function canResolveType(PortfolioGoal $portfolioGoal): bool
	{
		return $portfolioGoal->getType() === PortfolioGoalTypeEnum::TOTAL_DIVIDEND_AMOUNT;
	}

}
