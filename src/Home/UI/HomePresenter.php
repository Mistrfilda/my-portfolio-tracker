<?php

declare(strict_types = 1);

namespace App\Home\UI;

use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class HomePresenter extends BaseSysadminPresenter
{

	public function __construct(
		private readonly HomeGridFactory $homeGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Domovy';
	}

	protected function createComponentHomeGrid(): Datagrid
	{
		return $this->homeGridFactory->create();
	}

}
