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
		private ExpenseCategory|null $expenseCategory,
		private array $expenses,
		private SummaryPrice $summaryPrice,
	)
	{
	}

	public function getExpenseCategory(): ExpenseCategory|null
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

	public function getExpenseCategoryId(): int
	{
		return $this->expenseCategory?->getId() ?? 0;
	}

	public function getCategoryName(): string
	{
		return $this->expenseCategory?->getEnumName()->format() ?? 'Bez hlavního tagu';
	}

}
