<?php

declare(strict_types = 1);

namespace App\System\UI\Control;

use App\System\SystemValueResolveFacade;
use App\UI\Base\BaseControl;

class SystemValueControl extends BaseControl
{

	public function __construct(
		private SystemValueResolveFacade $systemValueResolveFacade,
	)
	{
	}

	public function render(): void
	{
		$this->template->values = $this->systemValueResolveFacade->getAllValues();
		$this->getTemplate()->setFile(__DIR__ . '/SystemValueControl.latte');
		$this->getTemplate()->render();
	}

}
