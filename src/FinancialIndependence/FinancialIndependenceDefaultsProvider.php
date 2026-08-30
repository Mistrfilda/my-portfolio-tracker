<?php

declare(strict_types = 1);

namespace App\FinancialIndependence;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

final readonly class FinancialIndependenceDefaultsProvider
{

	public const float DEFAULT_ANNUAL_NOMINAL_RETURN_PERCENTAGE = 6.0;

	public const float DEFAULT_ANNUAL_INFLATION_PERCENTAGE = 2.5;

	public const float DEFAULT_ANNUAL_COST_PERCENTAGE = 0.3;

	public const float DEFAULT_WITHDRAWAL_RATE_PERCENTAGE = 3.5;

	public const int DEFAULT_BIRTH_YEAR = 1996;

	public const int DEFAULT_PLANNED_LIFESPAN_AGE = 80;

	private const int AVERAGE_MONTH_COUNT = 12;

	public function __construct(
		private AssetPriceSummaryFacade $assetPriceSummaryFacade,
		private PortfolioPerformanceMonthRepository $portfolioPerformanceMonthRepository,
		private PortfolioPerformanceProvider $portfolioPerformanceProvider,
		private BankExpenseRepository $bankExpenseRepository,
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function provide(): FinancialIndependenceDefaults
	{
		$periodEndExclusive = $this->datetimeFactory->createToday()->modify('first day of this month');
		$periodStart = $periodEndExclusive->deductMonthsFromDatetime(self::AVERAGE_MONTH_COUNT);
		$historicalPerformanceSummary = $this->portfolioPerformanceProvider->getAllTimeSummary();
		$historicalAnnualizedTotalReturnPercentage = $historicalPerformanceSummary
			?->getAnnualizedTimeWeightedReturn();

		$expenseTotalCzk = 0.0;
		$expenseTransactionCount = 0;
		$missingCurrencyConversionCount = 0;
		foreach ($this->bankExpenseRepository->findByEffectiveDateRangeExcludingCategory(
			$periodStart,
			$periodEndExclusive,
			ExpenseCategoryEnum::INVESTMENT,
		) as $expense) {
			$expenseAmount = abs($expense->getAmount());
			if ($expense->getCurrency() !== CurrencyEnum::CZK) {
				try {
					$expenseAmount = $this->currencyConversionFacade->convertSimpleValue(
						$expenseAmount,
						$expense->getCurrency(),
						CurrencyEnum::CZK,
						$expense->getDate(),
					);
				} catch (MissingCurrencyPairException) {
					$missingCurrencyConversionCount++;
					continue;
				}
			}

			$expenseTotalCzk += $expenseAmount;
			$expenseTransactionCount++;
		}

		$contributionTotalCzk = 0.0;
		$contributionRecordCount = 0;
		foreach ($this->portfolioPerformanceMonthRepository->findAllOrdered() as $performanceMonth) {
			if (!$this->isInPeriod($performanceMonth->getPeriodMonth(), $periodStart, $periodEndExclusive)) {
				continue;
			}

			$contributionTotalCzk += $performanceMonth->getExternalContribution();
			$contributionRecordCount++;
		}

		$invoicedIncomeTotalCzk = 0.0;
		$invoicedIncomeRecordCount = 0;
		foreach ($this->workMonthlyIncomeRepository->findByYearAndMonth(null, null) as $workMonthlyIncome) {
			if (!$this->isYearAndMonthInPeriod(
				$workMonthlyIncome->getYear(),
				$workMonthlyIncome->getMonth(),
				$periodStart,
				$periodEndExclusive,
			)) {
				continue;
			}

			$invoicedIncomeTotalCzk += $workMonthlyIncome->getSummaryPrice()->getPrice();
			$invoicedIncomeRecordCount++;
		}

		return new FinancialIndependenceDefaults(
			new FinancialIndependenceCalculationInput(
				currentPortfolioValueCzk: $this->assetPriceSummaryFacade->getCurrentValue(
					CurrencyEnum::CZK,
				)->getPrice(),
				targetMonthlyExpensesCzk: $expenseTotalCzk / self::AVERAGE_MONTH_COUNT,
				monthlyContributionCzk: $contributionTotalCzk / self::AVERAGE_MONTH_COUNT,
				annualNominalReturnPercentage: $historicalAnnualizedTotalReturnPercentage
					?? self::DEFAULT_ANNUAL_NOMINAL_RETURN_PERCENTAGE,
				annualInflationPercentage: self::DEFAULT_ANNUAL_INFLATION_PERCENTAGE,
				annualCostPercentage: self::DEFAULT_ANNUAL_COST_PERCENTAGE,
				withdrawalRatePercentage: self::DEFAULT_WITHDRAWAL_RATE_PERCENTAGE,
				birthYear: self::DEFAULT_BIRTH_YEAR,
				plannedLifespanAge: self::DEFAULT_PLANNED_LIFESPAN_AGE,
			),
			$periodStart,
			$periodEndExclusive,
			$expenseTransactionCount,
			$contributionRecordCount,
			$invoicedIncomeTotalCzk / self::AVERAGE_MONTH_COUNT,
			$invoicedIncomeRecordCount,
			$missingCurrencyConversionCount,
			$historicalAnnualizedTotalReturnPercentage,
			$historicalPerformanceSummary?->getStartDate(),
			$historicalPerformanceSummary?->getEndDate(),
		);
	}

	private function isInPeriod(
		ImmutableDateTime $date,
		ImmutableDateTime $periodStart,
		ImmutableDateTime $periodEndExclusive,
	): bool
	{
		return $date->getTimestamp() >= $periodStart->getTimestamp()
			&& $date->getTimestamp() < $periodEndExclusive->getTimestamp();
	}

	private function isYearAndMonthInPeriod(
		int $year,
		int $month,
		ImmutableDateTime $periodStart,
		ImmutableDateTime $periodEndExclusive,
	): bool
	{
		$yearMonth = ($year * 12) + $month;
		$startYearMonth = ($periodStart->getYear() * 12) + $periodStart->getMonth();
		$endYearMonth = ($periodEndExclusive->getYear() * 12) + $periodEndExclusive->getMonth();

		return $yearMonth >= $startYearMonth && $yearMonth < $endYearMonth;
	}

}
