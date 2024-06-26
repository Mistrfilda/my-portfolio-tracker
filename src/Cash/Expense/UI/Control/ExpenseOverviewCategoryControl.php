<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Currency\CurrencyEnum;
use App\UI\Base\BaseControl;

class ExpenseOverviewCategoryControl extends BaseControl
{

	public function __construct(
		private int $year,
		private int|null $month,
		private BankExpenseRepository $bankExpenseRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
	)
	{
	}

	public function render(): void
	{
		$data = [];

		$totalSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$investmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		$excludedInvestmentSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);

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
			}

			$data[] = new ExpanseOverviewData($expenseCategory, $expenses, $summaryPrice);
		}

		usort(
			$data,
			static fn ($item1, $item2): int => $item1->getSummaryPrice()->getPrice() <=> $item2->getSummaryPrice()->getPrice(),
		);

		$this->getTemplate()->totalSummaryPrice = $totalSummaryPrice;
		$this->getTemplate()->investmentSummaryPrice = $investmentSummaryPrice;
		$this->getTemplate()->excludedInvestmentSummaryPrice = $excludedInvestmentSummaryPrice;
		$this->getTemplate()->data = $data;
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
