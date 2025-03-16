<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account\UI;

use App\Cash\Bank\Account\BankAccountFacade;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use App\Utils\TypeValidator;
use Ramsey\Uuid\UuidInterface;

class BankAccountFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private BankAccountRepository $bankAccountRepository,
		private BankAccountFacade $bankAccountFacade,
	)
	{
	}

	public function create(UuidInterface|null $id, callable $onSuccess): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('name', 'Název');
		$form->addText('bank', 'Banka');
		$form->addSelect('type', 'Typ', [
			BankAccountTypeEnum::BUSINESS->value => BankAccountTypeEnum::BUSINESS->format(),
			BankAccountTypeEnum::PERSONAL->value => BankAccountTypeEnum::PERSONAL->format(),
		]);

		$form->addSubmit('submit', 'Odeslat');

		if ($id !== null) {
			$bankAccount = $this->bankAccountRepository->getById($id);
			$form->setDefaults([
				'name' => $bankAccount->getName(),
				'bank' => $bankAccount->getBank(),
				'type' => $bankAccount->getType()->value,
			]);
		}

		$form->onSuccess[] = function (AdminForm $form) use ($id, $onSuccess): void {
			$values = $form->getValues();
			if ($id !== null) {
				$this->bankAccountFacade->update(
					$id,
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->bank),
					BankAccountTypeEnum::from(TypeValidator::validateString($values->type)),
				);
			} else {
				$this->bankAccountFacade->create(
					TypeValidator::validateString($values->name),
					TypeValidator::validateString($values->bank),
					BankAccountTypeEnum::from(TypeValidator::validateString($values->type)),
				);
			}

			$onSuccess();
		};

		return $form;
	}

}
