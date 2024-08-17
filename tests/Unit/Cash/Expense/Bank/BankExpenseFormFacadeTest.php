<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Expense\Bank;

use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpenseFormFacade;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Currency\CurrencyEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;

// Unit test for class BankExpenseFormFacade and specifically for method 'create'
class BankExpenseFormFacadeTest extends TestCase
{

	protected function setUp(): void
	{
		parent::setUp();
	}

	public function testCreate(): void
	{
		// mock dependencies for class we are testing
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$datetimeFactoryMock->shouldIgnoreMissing();
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);

		// initialize the class we are testing
		$bankExpenseFormFacade = new BankExpenseFormFacade(
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
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

		// We expect that the persist and flush methods will be called once during the test
		$entityManagerMock
			->shouldReceive('persist')
			->once();

		$entityManagerMock
			->shouldReceive('flush')
			->once();

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

}
