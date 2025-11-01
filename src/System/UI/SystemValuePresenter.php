<?php

declare(strict_types = 1);

namespace App\System\UI;

use App\System\UI\Control\SystemValueControl;
use App\System\UI\Control\SystemValueControlFactory;
use App\UI\Base\BaseAdminPresenter;

class SystemValuePresenter extends BaseAdminPresenter
{

	public function __construct(
		private SystemValueControlFactory $systemValueControlFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Systémové informace';
	}

	protected function createComponentSystemValueControl(): SystemValueControl
	{
		return $this->systemValueControlFactory->create();
	}

}
