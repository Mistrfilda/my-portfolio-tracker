<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Bank\Expense;

use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\Account\BankAccountRepository;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseFormFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class BankExpenseFormFacadeTest extends TestCase
{

	public function testCreate(): void
	{
		// mock dependencies for class we are testing
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$datetimeFactoryMock->shouldReceive('createNow')->andReturn(new ImmutableDateTime('now'));
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$bankAccountRepository = Mockery::mock(BankAccountRepository::class);

		// initialize the class we are testing
		$bankExpenseFormFacade = new BankExpenseFormFacade(
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$bankAccountRepository,
		);

		// test data for method arguments
		$identifier = 'test-identifier';
		$source = BankSourceEnum::KOMERCNI_BANKA;
		$transactionType = BankTransactionType::CARD_PAYMENT;
		$amount = 100.0;
		$currency = CurrencyEnum::USD;
		$settlementDate = new ImmutableDateTime('now');
		$transactionDate = new ImmutableDateTime('now');
		$transactionRawContent = 'test-transaction-raw-content';
		$bankAccountId = Uuid::uuid4();

		// We expect that the persist and flush methods will be called once during the test
		$entityManagerMock
			->shouldReceive('persist')
			->once();

		$entityManagerMock
			->shouldReceive('flush')
			->once();

		$bankAccountRepository->shouldReceive('getById')->andReturn(Mockery::mock(BankAccount::class));

		// call the method we are testing
		$bankExpense = $bankExpenseFormFacade->create(
			$identifier,
			$source,
			$transactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$bankAccountId,
		);

		// validate the result
		$this->assertEquals($identifier, $bankExpense->getIdentifier());
		$this->assertEquals($source, $bankExpense->getSource());
		$this->assertEquals($transactionType, $bankExpense->getBankTransactionType());
		$this->assertEquals($amount, $bankExpense->getAmount());
		$this->assertEquals($currency, $bankExpense->getCurrency());
		$this->assertEquals($settlementDate, $bankExpense->getSettlementDate());
		$this->assertEquals($transactionDate, $bankExpense->getTransactionDate());
		$this->assertEquals($transactionRawContent, $bankExpense->getTransactionRawContent());
	}

	public function testCreateWithNullIdentifier(): void
	{
		// mock dependencies
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$datetimeFactoryMock->shouldReceive('createNow')->andReturn(new ImmutableDateTime('now'));
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$bankAccountRepository = Mockery::mock(BankAccountRepository::class);

		// initialize facade
		$bankExpenseFormFacade = new BankExpenseFormFacade(
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$bankAccountRepository,
		);

		// test data
		$source = BankSourceEnum::KOMERCNI_BANKA;
		$transactionType = BankTransactionType::CARD_PAYMENT;
		$amount = 250.5;
		$currency = CurrencyEnum::CZK;
		$settlementDate = new ImmutableDateTime('2024-01-15');
		$transactionDate = new ImmutableDateTime('2024-01-15');
		$transactionRawContent = 'auto-generated-test';
		$bankAccountId = Uuid::uuid4();

		// Setup expectations
		$entityManagerMock->shouldReceive('persist')->once();
		$entityManagerMock->shouldReceive('flush')->once();
		$bankAccountRepository->shouldReceive('getById')->andReturn(Mockery::mock(BankAccount::class));

		// Execute with null identifier
		$bankExpense = $bankExpenseFormFacade->create(
			null,
			$source,
			$transactionType,
			$amount,
			$currency,
			$settlementDate,
			$transactionDate,
			$transactionRawContent,
			$bankAccountId,
		);

		// Validate - identifier should be auto-generated
		$this->assertNotNull($bankExpense->getIdentifier());
		$this->assertIsString($bankExpense->getIdentifier());
	}

	public function testUpdate(): void
	{
		// mock dependencies
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$datetimeFactoryMock->shouldReceive('createNow')->andReturn(new ImmutableDateTime('now'));
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$bankAccountRepository = Mockery::mock(BankAccountRepository::class);

		// initialize facade
		$bankExpenseFormFacade = new BankExpenseFormFacade(
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$bankAccountRepository,
		);

		// Create existing expense mock
		$expenseId = Uuid::uuid4();
		$existingBankAccount = Mockery::mock(BankAccount::class);
		$existingExpense = new BankExpense(
			'old-identifier',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			50.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2024-01-01'),
			new ImmutableDateTime('2024-01-01'),
			'old content',
			new ImmutableDateTime('now'),
			$existingBankAccount,
		);

		// test data for update
		$newIdentifier = 'updated-identifier';
		$newSource = BankSourceEnum::KOMERCNI_BANKA;
		$newTransactionType = BankTransactionType::CARD_PAYMENT;
		$newAmount = 150.0;
		$newCurrency = CurrencyEnum::EUR;
		$newSettlementDate = new ImmutableDateTime('2024-02-01');
		$newTransactionDate = new ImmutableDateTime('2024-02-01');
		$newTransactionRawContent = 'updated content';
		$newBankAccountId = Uuid::uuid4();
		$newBankAccount = Mockery::mock(BankAccount::class);

		// Setup expectations
		$bankExpenseRepositoryMock->shouldReceive('getById')
			->once()
			->with($expenseId)
			->andReturn($existingExpense);

		$bankAccountRepository->shouldReceive('getById')
			->once()
			->with($newBankAccountId)
			->andReturn($newBankAccount);

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute update
		$updatedExpense = $bankExpenseFormFacade->update(
			$expenseId,
			$newIdentifier,
			$newSource,
			$newTransactionType,
			$newAmount,
			$newCurrency,
			$newSettlementDate,
			$newTransactionDate,
			$newTransactionRawContent,
			$newBankAccountId,
		);

		// Validate
		$this->assertEquals($newIdentifier, $updatedExpense->getIdentifier());
		$this->assertEquals($newTransactionType, $updatedExpense->getBankTransactionType());
		$this->assertEquals($newAmount, $updatedExpense->getAmount());
		$this->assertEquals($newCurrency, $updatedExpense->getCurrency());
	}

}
