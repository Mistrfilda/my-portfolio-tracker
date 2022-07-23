<?php

declare(strict_types = 1);

namespace App\Admin\UI;

use App\Admin\AppAdmin;
use App\Admin\AppAdminFacade;
use App\Admin\AppAdminRepository;
use App\Doctrine\NoEntityFoundException;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;
use function assert;

class AppAdminFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private AppAdminFacade $appAdminFacade,
		private AppAdminRepository $appAdminRepository,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$username = $form->addText('username', 'Uživatelské jméno');

		$form->addText('email', 'Email')
			->setRequired()
			->addRule(Form::EMAIL);
		$form->addText('name', 'Jméno')
			->setRequired();

		$password = $form->addPassword('password', 'Heslo');
		$password
			->setNullable()
			->addCondition(Form::FILLED)
			->addRule(Form::MIN_LENGTH, 'Minimální počet znaků je %d', 6);

		$form->addPassword('passwordRepeat', 'Heslo znovu')
			->addConditionOn($password, Form::FILLED)
			->setRequired()
			->addRule(Form::EQUAL, 'Hesla se neshodují', $password);

		$form->addCheckbox('forceNewPassword', 'Vyžadovat změnu hesla');
		$form->addCheckbox('sysAdmin', 'Systém administrátor');

		$form->onValidate[] = function (Form $form) use ($id): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);
			if ($id === null) {
				try {
					$this->appAdminRepository->findByUsername($values->username);
					$form['username']->addError('Zadané uživatelské jméno již existuje');

					return;
				} catch (NoEntityFoundException) {
					//expected
				}
			}

			try {
				$user = $this->appAdminRepository->findByEmail($values->email);
				if ($id === null || ($user->getId()->toString() !== $id->toString())) {
					$form['email']->addError('Zadaný email již existuje');

					return;
				}
			} catch (NoEntityFoundException) {
				//expected
			}
		};

		$form->onSuccess[] = function (Form $form) use ($id, $onSuccess): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id !== null) {
				$this->appAdminFacade->updateAppAdmin(
					$id,
					$values->name,
					$values->email,
					$values->password,
					$values->forceNewPassword,
					$values->sysAdmin,
				);
			} else {
				$this->appAdminFacade->createAppAdmin(
					$values->name,
					$values->username,
					$values->email,
					$values->password,
					$values->forceNewPassword,
					$values->sysAdmin,
				);
			}

			$onSuccess();
		};

		if ($id === null) {
			$username->setRequired();
			$password->setRequired();
		} else {
			$username->setDisabled();
			$this->setDefaults($form, $this->appAdminRepository->findById($id));
		}

		$form->addSubmit('submit', 'Uložit');

		return $form;
	}

	private function setDefaults(Form $form, AppAdmin $appAdmin): void
	{
		$form->setDefaults([
			'username' => $appAdmin->getUsername(),
			'email' => $appAdmin->getEmail(),
			'name' => $appAdmin->getName(),
			'forceNewPassword' => $appAdmin->isNewPasswordForced(),
			'sysAdmin' => $appAdmin->isSysAdmin(),
		]);
	}

}
