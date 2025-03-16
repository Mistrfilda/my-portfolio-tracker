<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account;

use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;

class BankAccountFacade
{

	public function __construct(
		private EntityManagerInterface $entityManager,
		private BankAccountRepository $bankAccountRepository,
	)
	{
	}

	public function create(string $name, string $bank, BankAccountTypeEnum $type): void
	{
		$bankAccount = new BankAccount($name, $bank, $type);

		$this->entityManager->persist($bankAccount);
		$this->entityManager->flush();
	}

	public function update(
		UuidInterface $id,
		string $name,
		string $bank,
		BankAccountTypeEnum $type,
	): void
	{
		$bankAccount = $this->bankAccountRepository->getById($id);
		$bankAccount->update($name, $bank, $type);
		$this->entityManager->flush();
	}

}
