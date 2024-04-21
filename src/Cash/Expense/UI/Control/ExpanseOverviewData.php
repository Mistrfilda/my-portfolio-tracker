<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Category\ExpenseCategory;

class ExpanseOverviewData
{

	/**
	 * @param array<BankExpense> $expenses
	 */
	public function __construct(
		private ExpenseCategory $expenseCategory,
		private array $expenses,
		private SummaryPrice $summaryPrice,
	)
	{
	}

	public function getExpenseCategory(): ExpenseCategory
	{
		return $this->expenseCategory;
	}

	/**
	 * @return array<BankExpense>
	 */
	public function getExpenses(): array
	{
		return $this->expenses;
	}

	public function getSummaryPrice(): SummaryPrice
	{
		return $this->summaryPrice;
	}

}
