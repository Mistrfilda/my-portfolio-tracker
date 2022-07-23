<?php

declare(strict_types = 1);

namespace App\Login\UI\Form;

use App\Admin\CurrentAppAdminGetter;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Security\AuthenticationException;
use function assert;

class LoginFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('username', 'Username')
			->setRequired();

		$form->addPassword('password', 'Password')
			->setRequired();

		$form->addSubmit('submit', 'Submit');

		$form->onSuccess[] = function (AdminForm $form) use ($onSuccess): void {
			$values = $form->getValues(LoginFormDTO::class);
			assert($values instanceof LoginFormDTO);

			try {
				$this->currentAppAdminGetter->login($values->username, $values->password);
			} catch (AuthenticationException) {
				$form->addError('Nesprávná kombinace uživatelského jména a hesla');

				return;
			}

			$onSuccess();
		};

		return $form;
	}

}
