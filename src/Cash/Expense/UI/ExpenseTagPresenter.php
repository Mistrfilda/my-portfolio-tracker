<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Tag\UI\ExpenseTagDatagridFactory;
use App\Cash\Expense\Tag\UI\ExpenseTagFormFactory;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;

class ExpenseTagPresenter extends BaseAdminPresenter
{

	public function __construct(
		private ExpenseTagFormFactory $expenseTagFormFactory,
		private ExpenseTagDatagridFactory $expenseTagDatagridFactory,
	)
	{
		parent::__construct();
	}

	public function renderTags(): void
	{
		$this->template->heading = 'Výdajové tagy';
	}

	public function renderEditTag(int|null $id): void
	{
		if ($id !== null) {
			$this->template->heading = 'Úprava tagu';
		} else {
			$this->template->heading = 'Přidání tagu';
		}
	}

	public function createComponentExpenseTagForm(): AdminForm
	{
		$onSuccess = function (): void {
			$this->flashMessage('Úspěšně uloženo');
			$this->redirect('tags');
		};

		return $this->expenseTagFormFactory->create($this->processParameterInt(), $onSuccess);
	}

	public function createComponentExpenseTagGrid(): Datagrid
	{
		return $this->expenseTagDatagridFactory->create();
	}

}
