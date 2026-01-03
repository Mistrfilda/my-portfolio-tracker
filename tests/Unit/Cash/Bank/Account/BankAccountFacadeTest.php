<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Bank\Account;

use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountFacade;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\Account\BankAccountTypeEnum;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mockery;
use Ramsey\Uuid\Uuid;

class BankAccountFacadeTest extends UpdatedTestCase
{

	private BankAccountFacade $bankAccountFacade;

	private EntityManagerInterface $entityManager;

	private BankAccountRepository $bankAccountRepository;

	protected function setUp(): void
	{
		$this->entityManager = Mockery::mock(EntityManagerInterface::class);
		$this->bankAccountRepository = Mockery::mock(BankAccountRepository::class);

		$this->bankAccountFacade = new BankAccountFacade(
			$this->entityManager,
			$this->bankAccountRepository,
		);
	}

	public function testCreate(): void
	{
		$name = 'Test Account';
		$bank = 'Test Bank';
		$type = BankAccountTypeEnum::PERSONAL;

		$this->entityManager
			->shouldReceive('persist')
			->once()
			->with(Mockery::on(static fn (BankAccount $bankAccount): bool => $bankAccount->getName() === $name
					&& $bankAccount->getBank() === $bank
					&& $bankAccount->getType() === $type));

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->assertNoError(fn () =>
		$this->bankAccountFacade->create($name, $bank, $type));
	}

	public function testCreateBusinessAccount(): void
	{
		$name = 'Business Account';
		$bank = 'Business Bank';
		$type = BankAccountTypeEnum::BUSINESS;

		$this->entityManager
			->shouldReceive('persist')
			->once()
			->with(Mockery::on(static fn (BankAccount $bankAccount): bool => $bankAccount->getType() === $type));

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->assertNoError(fn () => $this->bankAccountFacade->create($name, $bank, $type));
	}

	public function testUpdate(): void
	{
		$id = Uuid::uuid4();
		$name = 'Updated Account';
		$bank = 'Updated Bank';
		$type = BankAccountTypeEnum::BUSINESS;

		$bankAccount = new BankAccount('Original', 'Original Bank', BankAccountTypeEnum::PERSONAL);

		$this->bankAccountRepository
			->shouldReceive('getById')
			->once()
			->with($id)
			->andReturn($bankAccount);

		$this->entityManager
			->shouldReceive('flush')
			->once();

		$this->bankAccountFacade->update($id, $name, $bank, $type);

		$this->assertSame($name, $bankAccount->getName());
		$this->assertSame($bank, $bankAccount->getBank());
		$this->assertSame($type, $bankAccount->getType());
	}

}
