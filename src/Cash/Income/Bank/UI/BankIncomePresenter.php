<?php

declare(strict_types = 1);


namespace App\Cash\Income\Bank\UI;


use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Datagrid\Datagrid;


class BankIncomePresenter extends BaseAdminPresenter
{
	public function __construct(
		private BankIncomeGridFactory $bankIncomeGridFactory
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Příjmy na bankovní účet';
	}

	public function createComponentBankIncomeGrid(): Datagrid
	{
		return $this->bankIncomeGridFactory->create();
	}
}
