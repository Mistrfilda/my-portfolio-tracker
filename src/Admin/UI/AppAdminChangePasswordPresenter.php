<?php

declare(strict_types = 1);

namespace App\Admin\UI;

use App\UI\Base\BaseAdminPresenter;
use App\UI\Control\Form\AdminForm;
use App\UI\FlashMessage\FlashMessageType;

class AppAdminChangePasswordPresenter extends BaseAdminPresenter
{

	public function __construct(
		private AppAdminChangePasswordFormFactory $adminChangePasswordFormFactory,
	)
	{
		parent::__construct();
	}

	public function renderDefault(): void
	{
		$this->template->heading = 'Změna hesla';
	}

	protected function createComponentAppAdminChangePasswordForm(): AdminForm
	{
		$onSuccess = function (): void {
			$this->flashMessage('Úspěšně jste si změnili heslo', FlashMessageType::SUCCESS);
			$this->redirect('Dashboard:default');
		};

		return $this->adminChangePasswordFormFactory->create($onSuccess);
	}

}
