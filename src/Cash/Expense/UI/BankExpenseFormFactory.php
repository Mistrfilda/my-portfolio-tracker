<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI;

use App\Cash\Expense\Bank\BankExpenseFormFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Bank\BankSourceEnum;
use App\Cash\Expense\Bank\BankTransactionType;
use App\Currency\CurrencyEnum;
use App\UI\Control\Form\AdminForm;
use App\UI\Control\Form\AdminFormFactory;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Ramsey\Uuid\UuidInterface;
use function assert;

class BankExpenseFormFactory
{

	public function __construct(
		private AdminFormFactory $adminFormFactory,
		private BankExpenseFormFacade $bankExpenseFormFacade,
		private BankExpenseRepository $bankExpenseRepository,
	)
	{
	}

	public function create(callable $onSuccess, UuidInterface|null $id): AdminForm
	{
		$form = $this->adminFormFactory->create();

		$form->addText('identifier', 'Identifikátor')
			->setNullable();

		$form->addSelect(
			'source',
			'Zdroj transkace',
			[BankSourceEnum::KOMERCNI_BANKA->value => BankSourceEnum::KOMERCNI_BANKA->format()],
		)->setRequired();

		$form->addSelect('type', 'Typ transkace', BankTransactionType::getOptionsForAdminSelect())
			->setRequired();

		$form->addFloat('amount', 'Částka');

		$form->addSelect('currency', 'Měna', CurrencyEnum::getOptionsForAdminSelect())
			->setRequired();

		$form->addDatePicker('settlementDate', 'Datum zaúčtování')->setRequired();
		$form->addDatePicker('transactionDate', 'Datum transkace')->setRequired();

		$form->addTextArea('transactionRawContent', 'Obsah transakce')->setRequired();

		$form->addSubmit('submit', 'Odeslat');

		if ($id !== null) {
			$bankExpense = $this->bankExpenseRepository->getById($id);
			$form->setDefaults([
				'identifier' => $bankExpense->getIdentifier(),
				'source' => $bankExpense->getSource()->value,
				'type' => $bankExpense->getBankTransactionType()->value,
				'amount' => $bankExpense->getAmount(),
				'currency' => $bankExpense->getCurrency()->value,
				'settlementDate' => $bankExpense->getSettlementDate(),
				'transactionDate' => $bankExpense->getTransactionDate(),
				'transactionRawContent' => $bankExpense->getTransactionRawContent(),
			]);
		}

		$form->onSuccess[] = function (Form $form) use ($onSuccess, $id): void {
			$values = $form->getValues(ArrayHash::class);
			assert($values instanceof ArrayHash);

			if ($id === null) {
				$this->bankExpenseFormFacade->create(
					$values->identifier,
					BankSourceEnum::from($values->source),
					BankTransactionType::from($values->type),
					$values->amount,
					CurrencyEnum::from($values->currency),
					$values->settlementDate,
					$values->transactionDate,
					$values->transactionRawContent,
				);
			} else {
				$this->bankExpenseFormFacade->update(
					$id,
					$values->identifier,
					BankSourceEnum::from($values->source),
					BankTransactionType::from($values->type),
					$values->amount,
					CurrencyEnum::from($values->currency),
					$values->settlementDate,
					$values->transactionDate,
					$values->transactionRawContent,
				);
			}

			$onSuccess();
		};

		return $form;
	}

}
