<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankSourceEnum;
use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class ExpensePresenter extends BaseAdminPresenter
{

	public function __construct(
		private BankExpenseFormFactory $expenseFormFactory,
		private BankExpenseGridFactory $bankExpenseGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderKb(): void
	{
		$this->template->heading = 'Výdaje';
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

	//  public function __construct(private KbPdfExpenseParser $kbPdfExpenseParser)
	//  {
	//      dump($this->kbPdfExpenseParser->parse( file_get_contents(__DIR__ . '/../511547780287_2_1132_20240208.pdf')));
	//      dump($this->kbPdfExpenseParser->parse(file_get_contents(__DIR__ . '/../511547780287_3_1132_20240308.pdf')));
	//      dump($this->kbPdfExpenseParser->parse(file_get_contents(__DIR__ . '/../511547780287_4_1132_20240408.pdf')));
	//      die();
	//  }

}
