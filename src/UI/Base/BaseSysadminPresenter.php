<?php

declare(strict_types = 1);

namespace App\UI\Base;

use Nette\Application\ForbiddenRequestException;

abstract class BaseSysadminPresenter extends BaseAdminPresenter
{

	public function startup(): void
	{
		parent::startup();
		if ($this->currentAppAdminGetter->getAppAdmin()->isSysAdmin() === false) {
			throw new ForbiddenRequestException();
		}
	}

}
