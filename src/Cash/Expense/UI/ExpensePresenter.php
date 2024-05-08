<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Bank\BankSourceEnum;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Modal\FrontModalControl;
use App\UI\Control\Modal\FrontModalControlFactory;
use App\UI\FlashMessage\FlashMessageType;
use Ramsey\Uuid\Uuid;

class ExpensePresenter extends BaseAdminPresenter
{

	protected bool $showModal = false;

	public function __construct(
		private BankExpenseUploadFormFactory $expenseFormFactory,
		private BankExpenseGridFactory $bankExpenseGridFactory,
		private FrontModalControlFactory $frontModalControlFactory,
		private BankExpenseRepository $bankExpenseRepository,
		private ExpenseTagRepository $expenseTagRepository,
		private ExpenseTagFacade $expenseTagFacade,
		private BankExpenseFormFactory $bankExpenseFormFactory,
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

	public function renderForm(string|null $id): void
	{
		if ($id !== null) {
			$this->template->heading = 'Editace výdaje';
		} else {
			$this->template->heading = 'Vytvoření výdaje';
		}
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

	public function createComponentBankExpenseForm(): AdminForm
	{
		$id = $this->processParameterUuid('id');

		$onSuccess = function () use ($id): void {
			if ($id !== null) {
				$this->flashMessage('Výdaj úspěšně upraven');
			} else {
				$this->flashMessage('Výdaj úspěšně nahrán');
			}

			$this->redirect('kb');
		};

		return $this->bankExpenseFormFactory->create($onSuccess, $id);
	}

	public function createComponentBankExpenseGrid(): Datagrid
	{
		return $this->bankExpenseGridFactory->create();
	}

	public function createComponentBankExpenseWithoutMainTagGrid(): Datagrid
	{
		return $this->bankExpenseGridFactory->create(true);
	}

	public function handleShowModal(string $id): void
	{
		$this->processModal($id);

	}

	public function handleChangeMainTag(string $expenseId, string $tagId): void
	{
		$this->expenseTagFacade->manuallySetMainTag(Uuid::fromString($expenseId), (int) $tagId);
		$this->processModal($expenseId);

		$this->redrawControl('bankExpenseGridSnippet');
		$this->redrawControl('bankExpenseWithoutMainTagGridSnippet');
	}

	public function handleAddOtherTag(string $expenseId, string $tagId): void
	{
		$this->expenseTagFacade->manuallySetOtherTag(Uuid::fromString($expenseId), (int) $tagId);

		$this->processModal($expenseId);

		$this->redrawControl('bankExpenseGridSnippet');
		$this->redrawControl('bankExpenseWithoutMainTagGridSnippet');
	}

	public function handleRemoveOtherTag(string $expenseId, string $tagId): void
	{
		$this->expenseTagFacade->manuallyRemoveOtherTag(Uuid::fromString($expenseId), (int) $tagId);

		$this->processModal($expenseId);

		$this->redrawControl('bankExpenseGridSnippet');
		$this->redrawControl('bankExpenseWithoutMainTagGridSnippet');
	}

	public function createComponentModal(): FrontModalControl
	{
		$modal = $this->frontModalControlFactory->create();
		$modal->setIncludeTemplateFile(__DIR__ . '/templates/Expense.modal.latte');
		return $modal;
	}

	protected function processModal(string $expenseId): void
	{
		$this->showModal = true;
		$modalComponent = $this->getComponent('modal');
		$modalComponent->setIncludedTemplateFileParameters(
			[
				'expense' => $this->bankExpenseRepository->getById(Uuid::fromString($expenseId)),
				'mainTags' => $this->expenseTagRepository->findAllMain(),
				'otherTags' => $this->expenseTagRepository->findAllOtherTags(),
				'changeMainTagHandleLink' => $this->link(
					'changeMainTag!',
					['expenseId' => 'replaceExpenseId', 'tagId' => 'replaceTagId'],
				),
				'changeOtherTagHandleLink' => $this->link(
					'addOtherTag!',
					['expenseId' => 'replaceExpenseId', 'tagId' => 'replaceTagId'],
				),
				'removeOtherTagHandlerLink' => $this->link(
					'removeOtherTag!',
					['expenseId' => 'replaceExpenseId', 'tagId' => 'replaceTagId'],
				),
			],
		);
		$this->redrawControl('modal');
	}

}
