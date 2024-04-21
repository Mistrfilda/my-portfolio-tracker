<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\UI\ExpenseOverviewPresenter;
use App\Currency\CurrencyEnum;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;

class ExpenseOverviewCategoryChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private BankExpenseRepository $bankExpenseRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private int|null $year = null,
		private int|null $month = null,
	)
	{

	}

	/**
	 * @param array<string, string> $parameters
	 */
	public function processParametersFromRequest(array $parameters): void
	{
		if (array_key_exists('originalRequestselectedYear', $parameters)) {
			$this->setYear((int) $parameters['originalRequestselectedYear']);
		}

		if (array_key_exists('originalRequestselectedMonth', $parameters)) {
			$this->setMonth((int) $parameters['originalRequestselectedMonth']);
		}

		if (array_key_exists('originalRequestdo', $parameters)) {
			if ($parameters['originalRequestdo'] === 'setYear') {
				if (array_key_exists('originalRequestselectedYear', $parameters)) {
					$this->setYear((int) $parameters['originalRequestselectedYear']);
				} else {
					$this->setYear(ExpenseOverviewPresenter::DEFAULT_YEAR);
				}
			}

			if (
				$parameters['originalRequestdo'] === 'setMonth'
				&& array_key_exists('originalRequestmonth', $parameters)
			) {
				$this->setMonth((int) $parameters['originalRequestmonth']);
			}
		}
	}

	public function setYear(int $year): void
	{
		$this->year = $year;
	}

	public function setMonth(int|null $month): void
	{
		$this->month = $month;
	}

	public function getChartData(): ChartDataSet
	{
		$data = new ChartData('Dataset 1');
		foreach ($this->expenseCategoryRepository->findAll() as $expenseCategory) {
			$expenses = $this->bankExpenseRepository->findByTagCategory($expenseCategory, $this->year, $this->month);

			$summaryPrice = new SummaryPrice(CurrencyEnum::CZK);
			foreach ($expenses as $expense) {
				$summaryPrice->addBankExpense($expense);
			}

			$data->add($expenseCategory->getEnumName()->format(), (int) $summaryPrice->getPrice());
		}

		return new ChartDataSet([$data], 'Kč');
	}

	public function getIdForChart(): string
	{
		return md5(self::class);
	}

}
