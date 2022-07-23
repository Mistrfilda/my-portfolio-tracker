<?php

declare(strict_types = 1);

namespace App\Login\UI;

use App\Login\UI\Form\LoginFormFactory;
use App\UI\Base\BaseFrontPresenter;
use App\UI\Control\Form\AdminForm;

class LoginPresenter extends BaseFrontPresenter
{

	/** @persistent */
	public string $backlink = '';

	public function __construct(private LoginFormFactory $loginFormFactory)
	{
		parent::__construct();
	}

	public function startup(): void
	{
		parent::startup();
		$this->useFullHeight = true;
	}

	protected function createComponentLoginForm(): AdminForm
	{
		$onSuccess = function (): void {
			$this->restoreRequest($this->backlink);
			$this->redirect('Dashboard:default');
		};

		return $this->loginFormFactory->create($onSuccess);
	}

}
