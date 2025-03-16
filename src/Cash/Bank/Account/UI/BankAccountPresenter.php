<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account\UI;

use App\Cash\Bank\Account\BankAccountRepository;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class BankAccountPresenter extends BaseAdminPresenter
{

	public function __construct(
		private BankAccountGridFactory $bankAccountGridFactory,
		private BankAccountRepository $bankAccountRepository,
		private BankAccountFormFactory $bankAccountFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Bankovní účty';
	}

	public function renderEdit(string|null $id): void
	{
		if ($id !== null) {
			$bankAccount = $this->bankAccountRepository->getById($this->processParameterRequiredUuid());
			$this->template->heading = sprintf(
				'Úprava bankovního účtu %s',
				$bankAccount->getName(),
			);
		} else {
			$this->template->heading = 'Přidání nového bankovního účtu';
		}
	}

	protected function createComponentBankAccountForm(): AdminForm
	{
		$id = $this->processParameterUuid();

		$onSuccess = function () use ($id): void {
			if ($id === null) {
				$this->flashMessage('Bankovní účet úspěšně vytvořen', FlashMessageType::SUCCESS);
			} else {
				$this->flashMessage('Bankovní účet úspěšně upraven', FlashMessageType::SUCCESS);
			}

			$this->redirect('BankAccount:default');
		};

		return $this->bankAccountFormFactory->create($id, $onSuccess);
	}

	protected function createComponentBankAccountGrid(): Datagrid
	{
		return $this->bankAccountGridFactory->create();
	}

}
