<?php

declare(strict_types = 1);

namespace App\Admin\UI;

use App\Admin\AppAdminFacade;
use App\Admin\CurrentAppAdminGetter;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Form;
use Nette\Security\Passwords;
use Nette\Utils\ArrayHash;
use function assert;

class AppAdminChangePasswordFormFactory
{

	public function __construct(
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private AppAdminFacade $appAdminFacade,
		private AdminFormFactory $adminFormFactory,
		private Passwords $passwords,
	)
	{
	}

	public function create(callable $onSuccess): AdminForm
	{
		$currentAdmin = $this->currentAppAdminGetter->getAppAdmin();
		$form = $this->adminFormFactory->create();

		if ($currentAdmin->isNewPasswordForced()) {
			$form->setHeading(
				'Změna hesla vyžadována',
				'Při prvotním přihlášení je nutné si změnit heslo',
			);
		}

		$form->addPassword('oldPassword', 'Staré heslo')
			->setRequired();

		$password = $form->addPassword('password', 'Nové heslo');
		$password
			->setRequired()
			->addRule(Form::MIN_LENGTH, 'Minimální počet znaků je %d', 6);

		$form->addPassword('passwordRepeat', 'Heslo znovu')
			->addConditionOn($password, Form::FILLED)
			->setRequired()
			->addRule(Form::EQUAL, 'Hesla se neshodují', $password);

		$form->onValidate[] = function (Form $form): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($this->passwords->verify(
				$values->oldPassword,
				$this->currentAppAdminGetter->getAppAdmin()->getPassword(),
			) === false) {
				$form['oldPassword']->addError('Nesprávné staré heslo');

				return;
			}
		};

		$form->onSuccess[] = function (Form $form) use ($onSuccess): void {
			$values = $form->getValues(AppAdminChangePasswordFormDTO::class);
			assert($values instanceof AppAdminChangePasswordFormDTO);

			$this->appAdminFacade->changeAppAdminPassword(
				$this->currentAppAdminGetter->getAppAdmin()->getId(),
				$values->password,
			);

			$onSuccess();
		};

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

}
