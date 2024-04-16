<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Bank\BankSourceEnum;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Modal\FrontModalControl;
use App\UI\Control\Modal\FrontModalControlFactory;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\Uuid;

class ExpensePresenter extends BaseAdminPresenter
{

	private bool $showModal = false;

	public function __construct(
		private BankExpenseFormFactory $expenseFormFactory,
		private BankExpenseGridFactory $bankExpenseGridFactory,
		private FrontModalControlFactory $frontModalControlFactory,
		private BankExpenseRepository $bankExpenseRepository,
	)
	{
		parent::__construct();
	}

	public function renderKb(): void
	{
		$this->template->heading = 'Výdaje';
		$this->template->showModal = $this->showModal;
	}

	public function renderKbForm(): void
	{
		$this->template->heading = 'Nahrání přehledu';
	}

	public function createComponentKbExpenseForm(): AdminForm
	{
		$onSuccess = function (bool $hasErrors): void {
			if ($hasErrors) {
				$this->flashMessage('Nahráno s chybami', FlashMessageType::DANGER);
				$this->redirect('this');
			} else {
				$this->flashMessage('Úspěšně nahráno');
				$this->redirect('kb');
			}
		};

		return $this->expenseFormFactory->create(
			BankSourceEnum::KOMERCNI_BANKA,
			$onSuccess,
		);
	}

	public function createComponentBankExpenseGrid(): Datagrid
	{
		return $this->bankExpenseGridFactory->create();
	}

	public function handleShowModal(string $id): void
	{
		$this->showModal = true;
		$modalComponent = $this->getComponent('modal');
		$modalComponent->setIncludedTemplateFileParameters(
			['expense' => $this->bankExpenseRepository->getById(Uuid::fromString($id))],
		);
		$this->redrawControl('modal');
	}

	public function createComponentModal(): FrontModalControl
	{
		$modal = $this->frontModalControlFactory->create();
		$modal->setIncludeTemplateFile(__DIR__ . '/templates/Expense.modal.latte');
		return $modal;
	}

}
