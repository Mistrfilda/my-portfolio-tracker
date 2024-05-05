<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Cash\Expense\UI\Control\ExpanseOverviewCategoryControlFactory;
use App\Cash\Expense\UI\Control\ExpenseOverviewCategoryChartDataProvider;
use App\Cash\Expense\UI\Control\ExpenseOverviewCategoryControl;
use App\UI\Control\Chart\ChartControl;
use App\UI\Control\Chart\ChartControlFactory;
use App\UI\Control\Chart\ChartType;
use App\UI\Control\Modal\FrontModalControlFactory;
use Nette\Application\Attributes\Persistent;

/**
 * @property-read ExpenseOverviewTemplate $template
 */
class ExpenseOverviewPresenter extends ExpensePresenter
{

	public const DEFAULT_YEAR = 2024;

	#[Persistent]
	public int $selectedYear = self::DEFAULT_YEAR;

	#[Persistent]
	public int|null $selectedMonth = null;

	public function __construct(
		private ExpanseOverviewCategoryControlFactory $expanseOverviewCategoryControlFactory,
		private ChartControlFactory $chartControlFactory,
		private ExpenseOverviewCategoryChartDataProvider $expenseOverviewCategoryChartDataProvider,
		BankExpenseUploadFormFactory $expenseFormFactory,
		BankExpenseGridFactory $bankExpenseGridFactory,
		FrontModalControlFactory $frontModalControlFactory,
		BankExpenseRepository $bankExpenseRepository,
		ExpenseTagRepository $expenseTagRepository,
		ExpenseTagFacade $expenseTagFacade,
		BankExpenseFormFactory $bankExpenseFormFactory,
	)
	{
		parent::__construct(
			$expenseFormFactory,
			$bankExpenseGridFactory,
			$frontModalControlFactory,
			$bankExpenseRepository,
			$expenseTagRepository,
			$expenseTagFacade,
			$bankExpenseFormFactory,
		);
	}

	public function renderDefault(int $selectedYear = self::DEFAULT_YEAR, int|null $selectedMonth = null): void
	{
		$this->template->heading = 'Přehled výdajů';
		$this->template->yearOptions = [2023, 2024];
		$this->template->monthOptions = [
			1 => 'Leden',
			2 => 'Únor',
			3 => 'Březen',
			4 => 'Duben',
			5 => 'Květen',
			6 => 'Červen',
			7 => 'Červenec',
			8 => 'Srpen',
			9 => 'Září',
			10 => 'Říjen',
			11 => 'Listopad',
			12 => 'Prosinec',
		];

		$this->template->selectedYear = $this->selectedYear;
		$this->template->selectedMonth = $this->selectedMonth;
		$this->template->showModal = $this->showModal;
	}

	public function handleSetYear(int $selectedYear = self::DEFAULT_YEAR): void
	{
		$this->selectedYear = $selectedYear;
		$this->invalidatePage();
	}

	public function handleSetMonth(int|null $month = null): void
	{
		$this->selectedMonth = $month;
		$this->invalidatePage();
	}

	protected function createComponentExpanseOverviewCategoryControl(): ExpenseOverviewCategoryControl
	{
		return $this->expanseOverviewCategoryControlFactory->create(
			$this->selectedYear ?? self::DEFAULT_YEAR,
			$this->selectedMonth,
		);
	}

	protected function createComponentChartOverview(): ChartControl
	{
		$dataProvider = clone $this->expenseOverviewCategoryChartDataProvider;
		$dataProvider->setYear($this->selectedYear ?? self::DEFAULT_YEAR,);
		$dataProvider->setMonth($this->selectedMonth);
		return $this->chartControlFactory->create(ChartType::DOUGHNUT, $dataProvider, true);
	}

}
