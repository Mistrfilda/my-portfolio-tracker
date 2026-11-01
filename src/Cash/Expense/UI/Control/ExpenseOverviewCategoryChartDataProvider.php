<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\UI\ExpenseOverviewPresenter;
use App\UI\Control\Chart\ChartData;
use App\UI\Control\Chart\ChartDataProvider;
use App\UI\Control\Chart\ChartDataSet;

class ExpenseOverviewCategoryChartDataProvider implements ChartDataProvider
{

	public function __construct(
		private BankExpenseRepository $bankExpenseRepository,
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
		if ($parameters === []) {
			return;
		}

		$this->setYear(
			array_key_exists('originalRequestselectedYear', $parameters)
				? (int) $parameters['originalRequestselectedYear']
				: ExpenseOverviewPresenter::DEFAULT_YEAR,
		);
		$this->setMonth(
			array_key_exists('originalRequestselectedMonth', $parameters)
				? (int) $parameters['originalRequestselectedMonth']
				: null,
		);
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
		$data = new ChartData('Výdaje');
		$overview = new ExpenseOverviewDataSet(
			$this->bankExpenseRepository->findForOverview($this->year, $this->month),
		);
		foreach ($overview->groups as $group) {
			$data->add($group->getCategoryName(), $group->getSummaryPrice()->getPrice());
		}

		return new ChartDataSet([$data], 'Kč');
	}

	public function getIdForChart(): string
	{
		return md5(self::class);
	}

}
