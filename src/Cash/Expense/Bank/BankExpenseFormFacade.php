<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BankExpenseFormFacade implements BankExpenseFacade
{

	public function __construct(
		private BankExpenseRepository $bankExpenseRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private BankAccountRepository $bankAccountRepository,
	)
	{
	}

	public function create(
		string|null $identifier,
		BankSourceEnum $source,
		BankTransactionType $bankTransactionType,
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		string $transactionRawContent,
		UuidInterface $bankAccountId,
	): BankExpense
	{
		if ($identifier === null) {
			$identifier = Uuid::uuid4()->toString();
		}

		$bankExpense = new BankExpense(
			$identifier,
			$source,
			$bankTransactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$this->datetimeFactory->createNow(),
			$this->bankAccountRepository->getById($bankAccountId),
		);

		$this->entityManager->persist($bankExpense);
		$this->entityManager->flush();
		return $bankExpense;
	}

	public function update(
		UuidInterface $id,
		string|null $identifier,
		BankSourceEnum $source,
		BankTransactionType $bankTransactionType,
		float $amount,
		CurrencyEnum $currency,
		ImmutableDateTime|null $settlementDate,
		ImmutableDateTime|null $transactionDate,
		string $transactionRawContent,
		UuidInterface $bankAccountId,
	): BankExpense
	{
		$bankExpense = $this->bankExpenseRepository->getById($id);

		$bankExpense->update(
			$identifier,
			$source,
			$bankTransactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$this->datetimeFactory->createNow(),
			$this->bankAccountRepository->getById($bankAccountId),
		);

		$this->entityManager->flush();

		return $bankExpense;
	}

}
