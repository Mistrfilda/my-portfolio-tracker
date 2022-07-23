<?php

declare(strict_types = 1);

namespace App\Admin\UI;

use App\UI\Base\BaseSysadminPresenter;
use App\UI\Control\Datagrid\Datagrid;

class AppAdminPresenter extends BaseSysadminPresenter
{

	public function __construct(
		private AppAdminGridFactory $productGridFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Uživatelé';
	}

	protected function createComponentAppAdminGrid(): Datagrid
	{
		return $this->productGridFactory->create();
	}

}
