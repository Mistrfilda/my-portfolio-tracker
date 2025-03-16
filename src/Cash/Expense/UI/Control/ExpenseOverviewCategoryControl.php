<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Income\Bank\BankIncomeRepository;
use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeRepository;
use App\Currency\CurrencyConversionFacade;
use App\Currency\CurrencyEnum;
use App\Stock\Dividend\Record\StockAssetDividendRecordRepository;
use App\UI\Base\BaseControl;

class ExpenseOverviewCategoryControl extends BaseControl
{

	public function __construct(
		private int $year,
		private int|null $month,
		private BankExpenseRepository $bankExpenseRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private BankIncomeRepository $bankIncomeRepository,
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private StockAssetDividendRecordRepository $stockAssetDividendRecordRepository,
		private CurrencyConversionFacade $currencyConversionFacade,
		private BankAccountRepository $bankAccountRepository,
	)
	{
	}

	public function render(): void
	{
		$data = [];

		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$investmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$excludedInvestmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$totalIncomeSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$totalWorkIncome = new SummaryPrice(CurrencyEnum::CZK);
		$totalDividendIncome = new SummaryPrice(CurrencyEnum::CZK);

		$bankAccountSummaryPrices = [];
		foreach ($this->bankAccountRepository->findAll() as $bankAccount) {
			$bankAccountSummaryPrices[$bankAccount->getId()->toString()] = new ExpenseOverviewBankAccountSummary(
				$bankAccount,
				new SummaryPrice(CurrencyEnum::CZK),
			);
		}

		foreach ($this->expenseCategoryRepository->findAll() as $expenseCategory) {
			$expenses = $this->bankExpenseRepository->findByTagCategory($expenseCategory, $this->year, $this->month);

			$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);
			foreach ($expenses as $expense) {
				$summaryPrice->addBankExpense($expense);
				if ($expense->getMainTag()?->getExpenseCategory()?->getExpenseCategoryEnum() === ExpenseCategoryEnum::INVESTMENT) {
					$investmentSummaryPrice->addBankExpense($expense);
				} else {
					$excludedInvestmentSummaryPrice->addBankExpense($expense);
				}

				$totalSummaryPrice->addBankExpense($expense);
				$bankAccountSummaryPrices[$expense->getBankAccount()->getId()->toString()]->getSummaryPrice()->addBankExpense(
					$expense,
				);
			}

			$data[] = new ExpanseOverviewData($expenseCategory, $expenses, $summaryPrice);
		}

		foreach ($this->bankIncomeRepository->findByYearAndMonthAndBankAccountType(
			$this->year,
			$this->month,
			BankAccountTypeEnum::PERSONAL,
		) as $bankIncome) {
			$totalIncomeSummaryPrice->addBankIncome($bankIncome);
		}

		foreach ($this->workMonthlyIncomeRepository->findByYearAndMonth(
			$this->year,
			$this->month,
		) as $workMonthlyIncome) {
			$totalWorkIncome->addWorkMonthlyIncome($workMonthlyIncome);

		}

		foreach ($this->stockAssetDividendRecordRepository->findByYearAndMonth(
			$this->year,
			$this->month,
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

		usort(
			$data,
			static fn ($item1, $item2): int => $item1->getSummaryPrice()->getPrice() <=> $item2->getSummaryPrice()->getPrice(),
		);

		$this->getTemplate()->totalSummaryPrice = $totalSummaryPrice;
		$this->getTemplate()->investmentSummaryPrice = $investmentSummaryPrice;
		$this->getTemplate()->excludedInvestmentSummaryPrice = $excludedInvestmentSummaryPrice;
		$this->getTemplate()->totalIncomeSummaryPrice = $totalIncomeSummaryPrice;
		$this->getTemplate()->totalWorkIncome = $totalWorkIncome;
		$this->getTemplate()->totalDividendIncome = $totalDividendIncome;
		$this->getTemplate()->data = $data;
		$this->getTemplate()->bankAccountSummaryPrices = $bankAccountSummaryPrices;
		$this->getTemplate()->setFile(str_replace('.php', '.latte', __FILE__));
		$this->getTemplate()->render();
	}

	public function redrawDetailTables(): void
	{
		$this->redrawControl('expenseOverviewArea');
		foreach ($this->expenseCategoryRepository->findAll() as $expenseCategory) {
			$this->redrawControl('bankExpenses-' . $expenseCategory->getId());
		}
	}

}
