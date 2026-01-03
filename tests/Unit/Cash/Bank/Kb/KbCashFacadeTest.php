<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Bank\Kb;

use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Bank\Kb\KbCashFacade;
use App\Cash\Bank\Kb\KbContentParserResult;
use App\Cash\Bank\Kb\KbCsvCashParser;
use App\Cash\Bank\Kb\KbPdfCashParser;
use App\Cash\Bank\Kb\KbSourceEnum;
use App\Cash\Bank\Kb\KbTransaction;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Income\Bank\BankIncomeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KbCashFacadeTest extends TestCase
{

	public function testProcessFileContentsWithCsvSuccessfully(): void
	{
		// Mock dependencies
		$kbPdfParserMock = Mockery::mock(KbPdfCashParser::class);
		$kbCsvParserMock = Mockery::mock(KbCsvCashParser::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$bankIncomeRepositoryMock = Mockery::mock(BankIncomeRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);

		// Create facade
		$facade = new KbCashFacade(
			$kbPdfParserMock,
			$kbCsvParserMock,
			$bankExpenseRepositoryMock,
			$bankIncomeRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$loggerMock,
		);

		// Prepare test data
		$fileContents = 'test csv content';
		$bankAccount = Mockery::mock(BankAccount::class);

		$transaction = new KbTransaction();
		$transaction->setBankTransactionType(BankTransactionType::CARD_PAYMENT);
		$transaction->setAmount(-100.0);
		$transaction->setSettlementDate('01.01.2024');
		$transaction->setTransactionDate('01.01.2024');
		$transaction->setTransactionRawContent('Test transaction');

		$parserResult = Mockery::mock(KbContentParserResult::class);
		$parserResult->shouldReceive('getProcessedTransactions')->andReturn([$transaction]);
		$parserResult->shouldReceive('getIncomingTransactions')->andReturn([]);
		$parserResult->shouldReceive('getUnprocessedTransactions')->andReturn([]);

		// Setup expectations
		$kbCsvParserMock->shouldReceive('parse')
			->once()
			->with($fileContents)
			->andReturn($parserResult);

		$bankExpenseRepositoryMock->shouldReceive('findByIdentifier')
			->once()
			->andReturn(null);

		$datetimeFactoryMock->shouldReceive('createNow')
			->andReturn(new ImmutableDateTime('now'));

		$entityManagerMock->shouldReceive('persist')->once();
		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$result = $facade->processFileContents($fileContents, KbSourceEnum::CSV, $bankAccount);

		// Assert
		$this->assertFalse($result);
	}

	public function testProcessFileContentsWithPdfSuccessfully(): void
	{
		// Mock dependencies
		$kbPdfParserMock = Mockery::mock(KbPdfCashParser::class);
		$kbCsvParserMock = Mockery::mock(KbCsvCashParser::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$bankIncomeRepositoryMock = Mockery::mock(BankIncomeRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);

		// Create facade
		$facade = new KbCashFacade(
			$kbPdfParserMock,
			$kbCsvParserMock,
			$bankExpenseRepositoryMock,
			$bankIncomeRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$loggerMock,
		);

		// Prepare test data
		$fileContents = 'test pdf content';
		$bankAccount = Mockery::mock(BankAccount::class);

		$incomeTransaction = new KbTransaction();
		$incomeTransaction->setBankTransactionType(BankTransactionType::TRANSACTION);
		$incomeTransaction->setAmount(1000.0);
		$incomeTransaction->setSettlementDate('01.01.2024');
		$incomeTransaction->setTransactionRawContent('Income transaction');

		$parserResult = Mockery::mock(KbContentParserResult::class);
		$parserResult->shouldReceive('getProcessedTransactions')->andReturn([]);
		$parserResult->shouldReceive('getIncomingTransactions')->andReturn([$incomeTransaction]);
		$parserResult->shouldReceive('getUnprocessedTransactions')->andReturn([]);

		// Setup expectations
		$kbPdfParserMock->shouldReceive('parse')
			->once()
			->with($fileContents)
			->andReturn($parserResult);

		$bankIncomeRepositoryMock->shouldReceive('findByIdentifier')
			->once()
			->andReturn(null);

		$datetimeFactoryMock->shouldReceive('createNow')
			->andReturn(new ImmutableDateTime('now'));

		$entityManagerMock->shouldReceive('persist')->once();
		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$result = $facade->processFileContents($fileContents, KbSourceEnum::PDF, $bankAccount);

		// Assert
		$this->assertFalse($result);
	}

	public function testProcessFileContentsWithUnprocessedTransactions(): void
	{
		// Mock dependencies
		$kbPdfParserMock = Mockery::mock(KbPdfCashParser::class);
		$kbCsvParserMock = Mockery::mock(KbCsvCashParser::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$bankIncomeRepositoryMock = Mockery::mock(BankIncomeRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);

		// Create facade
		$facade = new KbCashFacade(
			$kbPdfParserMock,
			$kbCsvParserMock,
			$bankExpenseRepositoryMock,
			$bankIncomeRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$loggerMock,
		);

		// Prepare test data
		$fileContents = 'test content';
		$bankAccount = Mockery::mock(BankAccount::class);

		$unprocessedTransaction = new KbTransaction();
		$unprocessedTransaction->setTransactionRawContent('Invalid transaction');

		$parserResult = Mockery::mock(KbContentParserResult::class);
		$parserResult->shouldReceive('getProcessedTransactions')->andReturn([]);
		$parserResult->shouldReceive('getIncomingTransactions')->andReturn([]);
		$parserResult->shouldReceive('getUnprocessedTransactions')->andReturn([$unprocessedTransaction]);

		// Setup expectations
		$kbCsvParserMock->shouldReceive('parse')
			->once()
			->with($fileContents)
			->andReturn($parserResult);

		$loggerMock->shouldReceive('critical')
			->once()
			->with('Some transactions were not processed', Mockery::type('array'));

		// Execute
		$result = $facade->processFileContents($fileContents, KbSourceEnum::CSV, $bankAccount);

		// Assert
		$this->assertTrue($result);
	}

	public function testProcessFileContentsSkipsDuplicateExpenses(): void
	{
		// Mock dependencies
		$kbPdfParserMock = Mockery::mock(KbPdfCashParser::class);
		$kbCsvParserMock = Mockery::mock(KbCsvCashParser::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$bankIncomeRepositoryMock = Mockery::mock(BankIncomeRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$loggerMock = Mockery::mock(LoggerInterface::class);

		// Create facade
		$facade = new KbCashFacade(
			$kbPdfParserMock,
			$kbCsvParserMock,
			$bankExpenseRepositoryMock,
			$bankIncomeRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$loggerMock,
		);

		// Prepare test data
		$fileContents = 'test content';
		$bankAccount = Mockery::mock(BankAccount::class);

		$transaction = new KbTransaction();
		$transaction->setBankTransactionType(BankTransactionType::CARD_PAYMENT);
		$transaction->setAmount(-100.0);
		$transaction->setSettlementDate('01.01.2024');
		$transaction->setTransactionDate('01.01.2024');
		$transaction->setTransactionRawContent('Test transaction');

		$parserResult = Mockery::mock(KbContentParserResult::class);
		$parserResult->shouldReceive('getProcessedTransactions')->andReturn([$transaction]);
		$parserResult->shouldReceive('getIncomingTransactions')->andReturn([]);
		$parserResult->shouldReceive('getUnprocessedTransactions')->andReturn([]);

		$kbCsvParserMock->shouldReceive('parse')
			->once()
			->with($fileContents)
			->andReturn($parserResult);

		$bankExpenseRepositoryMock->shouldReceive('findByIdentifier')
			->once()
			->andReturn(Mockery::mock(BankExpense::class));

		$entityManagerMock->shouldReceive('persist')->never();
		$entityManagerMock->shouldReceive('flush')->never();

		// Execute
		$result = $facade->processFileContents($fileContents, KbSourceEnum::CSV, $bankAccount);

		// Assert
		$this->assertFalse($result);
	}

}
