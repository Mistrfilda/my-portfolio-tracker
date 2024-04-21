<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\UI\Control\ExpanseOverviewCategoryControlFactory;
use App\Cash\Expense\UI\Control\ExpenseOverviewCategoryControl;
use App\UI\Base\BaseSysadminPresenter;
use Nette\Application\Attributes\Persistent;

/**
 * @property-read ExpenseOverviewTemplate $template
 */
class ExpenseOverviewPresenter extends BaseSysadminPresenter
{

	private const DEFAULT_YEAR = 2024;

	#[Persistent]
	public int $selectedYear = self::DEFAULT_YEAR;

	#[Persistent]
	public int|null $selectedMonth = null;

	public function __construct(
		private ExpanseOverviewCategoryControlFactory $expanseOverviewCategoryControlFactory,
	)
	{
		parent::__construct();
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

	public function invalidatePage(): void
	{
		if ($this->isAjax()) {
			$this->redrawControl();
		} else {
			$this->redirect('this');
		}
	}

	protected function createComponentExpanseOverviewCategoryControl(): ExpenseOverviewCategoryControl
	{
		return $this->expanseOverviewCategoryControlFactory->create(
			$this->selectedYear ?? self::DEFAULT_YEAR,
			$this->selectedMonth,
		);
	}

}
