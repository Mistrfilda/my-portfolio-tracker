<?php

declare(strict_types = 1);

namespace App\Test\Unit\FinancialIndependence;

use App\Asset\Price\AssetPriceSummaryFacade;
use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncome;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Currency\MissingCurrencyPairException;
use App\FinancialIndependence\FinancialIndependenceDefaultsProvider;
use App\Statistic\Performance\PortfolioPerformanceMonth;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Statistic\Performance\PortfolioPerformanceProvider;
use App\Statistic\Performance\PortfolioPerformanceSummary;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class FinancialIndependenceDefaultsProviderTest extends TestCase
{

	public function testProvideBuildsDefaultsFromLastTwelveCompletedMonths(): void
	{
		$today = new ImmutableDateTime('2026-08-28 00:00:00');
		$periodStart = new ImmutableDateTime('2025-08-01 00:00:00');
		$periodEndExclusive = new ImmutableDateTime('2026-08-01 00:00:00');
		$eurExpenseDate = new ImmutableDateTime('2026-02-14 00:00:00');
		$czkExpense = $this->createExpense(-12_000.0, CurrencyEnum::CZK, new ImmutableDateTime('2025-09-01'));
		$eurExpense = $this->createExpense(-100.0, CurrencyEnum::EUR, $eurExpenseDate);

		$assetPriceSummaryFacade = $this->createMock(AssetPriceSummaryFacade::class);
		$assetPriceSummaryFacade->expects(self::once())
			->method('getCurrentValue')
			->with(CurrencyEnum::CZK)
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 4_000_000.0));
		$bankExpenseRepository = $this->createMock(BankExpenseRepository::class);
		$bankExpenseRepository->expects(self::once())
			->method('findByEffectiveDateRangeExcludingCategory')
			->with(
				self::equalTo($periodStart),
				self::equalTo($periodEndExclusive),
				ExpenseCategoryEnum::INVESTMENT,
			)
			->willReturn([$czkExpense, $eurExpense]);
		$currencyConversionFacade = $this->createMock(CurrencyConversionFacade::class);
		$currencyConversionFacade->expects(self::once())
			->method('convertSimpleValue')
			->with(100.0, CurrencyEnum::EUR, CurrencyEnum::CZK, $eurExpenseDate)
			->willReturn(2_500.0);
		$historicalReturnPeriodStart = new ImmutableDateTime('2021-01-01');
		$historicalReturnPeriodEnd = new ImmutableDateTime('2026-08-01');
		$portfolioPerformanceProvider = $this->createMock(PortfolioPerformanceProvider::class);
		$portfolioPerformanceProvider->expects(self::once())
			->method('getAllTimeSummary')
			->willReturn(new PortfolioPerformanceSummary(
				$historicalReturnPeriodStart,
				$historicalReturnPeriodEnd,
				62.5,
				9.1,
				8.5,
				8.2,
			));

		$provider = new FinancialIndependenceDefaultsProvider(
			$assetPriceSummaryFacade,
			$this->createPerformanceMonthRepository([
				$this->createPerformanceMonth('2025-07-01', 999_999.0),
				$this->createPerformanceMonth('2025-08-01', 12_000.0),
				$this->createPerformanceMonth('2026-07-01', 24_000.0),
				$this->createPerformanceMonth('2026-08-01', 999_999.0),
			]),
			$portfolioPerformanceProvider,
			$bankExpenseRepository,
			$this->createWorkMonthlyIncomeRepository([
				$this->createWorkMonthlyIncome(2025, 7, 999_999.0),
				$this->createWorkMonthlyIncome(2025, 9, 120_000.0),
				$this->createWorkMonthlyIncome(2026, 7, 240_000.0),
				$this->createWorkMonthlyIncome(2026, 8, 999_999.0),
			]),
			$currencyConversionFacade,
			$this->createDatetimeFactory($today),
		);

		$defaults = $provider->provide();

		self::assertEquals($periodStart, $defaults->periodStart);
		self::assertEquals($periodEndExclusive, $defaults->periodEndExclusive);
		self::assertSame(2, $defaults->expenseTransactionCount);
		self::assertSame(2, $defaults->contributionRecordCount);
		self::assertSame(2, $defaults->invoicedIncomeRecordCount);
		self::assertSame(0, $defaults->missingCurrencyConversionCount);
		self::assertSame(30_000.0, $defaults->averageMonthlyInvoicedIncomeCzk);
		self::assertSame(4_000_000.0, $defaults->calculationInput->currentPortfolioValueCzk);
		self::assertEqualsWithDelta(
			1_208.333333,
			$defaults->calculationInput->targetMonthlyExpensesCzk,
			0.000001,
		);
		self::assertSame(3_000.0, $defaults->calculationInput->monthlyContributionCzk);
		self::assertSame(9.1, $defaults->calculationInput->annualNominalReturnPercentage);
		self::assertSame(2.5, $defaults->calculationInput->annualInflationPercentage);
		self::assertSame(0.3, $defaults->calculationInput->annualCostPercentage);
		self::assertSame(3.5, $defaults->calculationInput->withdrawalRatePercentage);
		self::assertSame(1996, $defaults->calculationInput->birthYear);
		self::assertSame(80, $defaults->calculationInput->plannedLifespanAge);
		self::assertSame(9.1, $defaults->historicalAnnualizedTotalReturnPercentage);
		self::assertSame($historicalReturnPeriodStart, $defaults->historicalReturnPeriodStart);
		self::assertSame($historicalReturnPeriodEnd, $defaults->historicalReturnPeriodEnd);
	}

	public function testProvideSkipsExpenseWithoutHistoricalCurrencyConversion(): void
	{
		$expense = $this->createExpense(-100.0, CurrencyEnum::USD, new ImmutableDateTime('2026-02-14'));
		$assetPriceSummaryFacade = $this->createStub(AssetPriceSummaryFacade::class);
		$assetPriceSummaryFacade->method('getCurrentValue')
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 0.0));
		$bankExpenseRepository = $this->createStub(BankExpenseRepository::class);
		$bankExpenseRepository->method('findByEffectiveDateRangeExcludingCategory')->willReturn([$expense]);
		$currencyConversionFacade = $this->createStub(CurrencyConversionFacade::class);
		$currencyConversionFacade->method('convertSimpleValue')
			->willThrowException(new MissingCurrencyPairException());
		$portfolioPerformanceProvider = $this->createStub(PortfolioPerformanceProvider::class);
		$portfolioPerformanceProvider->method('getAllTimeSummary')->willReturn(null);

		$provider = new FinancialIndependenceDefaultsProvider(
			$assetPriceSummaryFacade,
			$this->createPerformanceMonthRepository([]),
			$portfolioPerformanceProvider,
			$bankExpenseRepository,
			$this->createWorkMonthlyIncomeRepository([]),
			$currencyConversionFacade,
			$this->createDatetimeFactory(new ImmutableDateTime('2026-08-28')),
		);

		$defaults = $provider->provide();

		self::assertSame(0.0, $defaults->calculationInput->targetMonthlyExpensesCzk);
		self::assertSame(0, $defaults->expenseTransactionCount);
		self::assertSame(1, $defaults->missingCurrencyConversionCount);
		self::assertSame(
			FinancialIndependenceDefaultsProvider::DEFAULT_ANNUAL_NOMINAL_RETURN_PERCENTAGE,
			$defaults->calculationInput->annualNominalReturnPercentage,
		);
		self::assertNull($defaults->historicalAnnualizedTotalReturnPercentage);
		self::assertNull($defaults->historicalReturnPeriodStart);
		self::assertNull($defaults->historicalReturnPeriodEnd);
	}

	public function testProvideKeepsHistoricalPeriodWhenAnnualizedReturnIsUnavailable(): void
	{
		$historicalReturnPeriodStart = new ImmutableDateTime('2026-03-01');
		$historicalReturnPeriodEnd = new ImmutableDateTime('2026-08-01');
		$portfolioPerformanceProvider = $this->createStub(PortfolioPerformanceProvider::class);
		$portfolioPerformanceProvider->method('getAllTimeSummary')
			->willReturn(new PortfolioPerformanceSummary(
				$historicalReturnPeriodStart,
				$historicalReturnPeriodEnd,
				4.2,
				null,
				4.0,
				3.9,
			));
		$assetPriceSummaryFacade = $this->createStub(AssetPriceSummaryFacade::class);
		$assetPriceSummaryFacade->method('getCurrentValue')
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 0.0));
		$bankExpenseRepository = $this->createStub(BankExpenseRepository::class);
		$bankExpenseRepository->method('findByEffectiveDateRangeExcludingCategory')->willReturn([]);

		$provider = new FinancialIndependenceDefaultsProvider(
			$assetPriceSummaryFacade,
			$this->createPerformanceMonthRepository([]),
			$portfolioPerformanceProvider,
			$bankExpenseRepository,
			$this->createWorkMonthlyIncomeRepository([]),
			$this->createStub(CurrencyConversionFacade::class),
			$this->createDatetimeFactory(new ImmutableDateTime('2026-08-28')),
		);

		$defaults = $provider->provide();

		self::assertSame(
			FinancialIndependenceDefaultsProvider::DEFAULT_ANNUAL_NOMINAL_RETURN_PERCENTAGE,
			$defaults->calculationInput->annualNominalReturnPercentage,
		);
		self::assertNull($defaults->historicalAnnualizedTotalReturnPercentage);
		self::assertSame($historicalReturnPeriodStart, $defaults->historicalReturnPeriodStart);
		self::assertSame($historicalReturnPeriodEnd, $defaults->historicalReturnPeriodEnd);
	}

	public function testProvideUsesZeroHistoricalAnnualizedReturnInsteadOfFallback(): void
	{
		$portfolioPerformanceProvider = $this->createStub(PortfolioPerformanceProvider::class);
		$portfolioPerformanceProvider->method('getAllTimeSummary')
			->willReturn(new PortfolioPerformanceSummary(
				new ImmutableDateTime('2021-01-01'),
				new ImmutableDateTime('2026-08-01'),
				0.0,
				0.0,
				0.0,
				0.0,
			));
		$assetPriceSummaryFacade = $this->createStub(AssetPriceSummaryFacade::class);
		$assetPriceSummaryFacade->method('getCurrentValue')
			->willReturn(new SummaryPrice(CurrencyEnum::CZK, 0.0));
		$bankExpenseRepository = $this->createStub(BankExpenseRepository::class);
		$bankExpenseRepository->method('findByEffectiveDateRangeExcludingCategory')->willReturn([]);

		$provider = new FinancialIndependenceDefaultsProvider(
			$assetPriceSummaryFacade,
			$this->createPerformanceMonthRepository([]),
			$portfolioPerformanceProvider,
			$bankExpenseRepository,
			$this->createWorkMonthlyIncomeRepository([]),
			$this->createStub(CurrencyConversionFacade::class),
			$this->createDatetimeFactory(new ImmutableDateTime('2026-08-28')),
		);

		$defaults = $provider->provide();

		self::assertSame(0.0, $defaults->calculationInput->annualNominalReturnPercentage);
		self::assertSame(0.0, $defaults->historicalAnnualizedTotalReturnPercentage);
	}

	private function createExpense(
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime $date,
	): BankExpense
	{
		$expense = $this->createStub(BankExpense::class);
		$expense->method('getAmount')->willReturn($amount);
		$expense->method('getCurrency')->willReturn($currency);
		$expense->method('getDate')->willReturn($date);

		return $expense;
	}

	private function createPerformanceMonth(string $periodMonth, float $externalContribution): PortfolioPerformanceMonth
	{
		$month = $this->createStub(PortfolioPerformanceMonth::class);
		$month->method('getPeriodMonth')->willReturn(new ImmutableDateTime($periodMonth));
		$month->method('getExternalContribution')->willReturn($externalContribution);

		return $month;
	}

	/**
	 * @param array<PortfolioPerformanceMonth> $months
	 */
	private function createPerformanceMonthRepository(array $months): PortfolioPerformanceMonthRepository
	{
		$repository = $this->createStub(PortfolioPerformanceMonthRepository::class);
		$repository->method('findAllOrdered')->willReturn($months);

		return $repository;
	}

	private function createWorkMonthlyIncome(int $year, int $month, float $amount): WorkMonthlyIncome
	{
		$income = $this->createStub(WorkMonthlyIncome::class);
		$income->method('getYear')->willReturn($year);
		$income->method('getMonth')->willReturn($month);
		$income->method('getSummaryPrice')->willReturn(new SummaryPrice(CurrencyEnum::CZK, $amount));

		return $income;
	}

	/**
	 * @param array<WorkMonthlyIncome> $incomes
	 */
	private function createWorkMonthlyIncomeRepository(array $incomes): WorkMonthlyIncomeRepository
	{
		$repository = $this->createStub(WorkMonthlyIncomeRepository::class);
		$repository->method('findByYearAndMonth')->willReturn($incomes);

		return $repository;
	}

	private function createDatetimeFactory(ImmutableDateTime $today): DatetimeFactory
	{
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createToday')->willReturn($today);

		return $datetimeFactory;
	}

}
