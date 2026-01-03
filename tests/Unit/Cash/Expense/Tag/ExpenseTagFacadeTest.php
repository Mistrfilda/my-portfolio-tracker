<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Expense\Tag;

use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\Tag\ExpenseTagException;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\System\SystemValueFacade;
use App\Utils\Console\ConsoleCurrentOutputHelper;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class ExpenseTagFacadeTest extends TestCase
{

	public function testCreate(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Test data
		$name = 'Test Tag';
		$expenseCategoryId = 1;
		$regexes = ['test.*', 'example'];
		$isTax = false;
		$now = new ImmutableDateTime('now');

		$expenseCategory = Mockery::mock(ExpenseCategory::class);

		// Setup expectations
		$datetimeFactoryMock->shouldReceive('createNow')->andReturn($now);
		$expenseCategoryRepositoryMock->shouldReceive('getById')
			->once()
			->with($expenseCategoryId)
			->andReturn($expenseCategory);

		$entityManagerMock->shouldReceive('persist')->once();
		$entityManagerMock->shouldReceive('flush')->once();
		$entityManagerMock->shouldReceive('refresh')->once();

		// Execute
		$expenseTag = $facade->create($name, $expenseCategoryId, null, $regexes, $isTax);

		// Validate
		$this->assertInstanceOf(ExpenseTag::class, $expenseTag);
		$this->assertEquals($name, $expenseTag->getName());
		$this->assertEquals($regexes, $expenseTag->getRegexes());
		$this->assertEquals($isTax, $expenseTag->isTax());
	}

	public function testCreateWithParentTag(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Test data
		$name = 'Child Tag';
		$parentTagId = 5;
		$regexes = ['child.*'];
		$isTax = true;
		$now = new ImmutableDateTime('now');

		$parentTag = Mockery::mock(ExpenseTag::class);

		// Setup expectations
		$datetimeFactoryMock->shouldReceive('createNow')->andReturn($now);
		$expenseTagRepositoryMock->shouldReceive('getById')
			->once()
			->with($parentTagId)
			->andReturn($parentTag);

		$entityManagerMock->shouldReceive('persist')->once();
		$entityManagerMock->shouldReceive('flush')->once();
		$entityManagerMock->shouldReceive('refresh')->once();

		// Execute
		$expenseTag = $facade->create($name, null, $parentTagId, $regexes, $isTax);

		// Validate
		$this->assertInstanceOf(ExpenseTag::class, $expenseTag);
		$this->assertTrue($expenseTag->isTax());
	}

	public function testCreateThrowsExceptionWhenBothCategoryAndParentAreNull(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Expect exception
		$this->expectException(ExpenseTagException::class);
		$this->expectExceptionMessage('Expense category or parent tag must be filled');

		// Execute - should throw exception
		$facade->create('Test Tag', null, null, ['regex'], false);
	}

	public function testUpdate(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Create existing tag
		$expenseCategory = Mockery::mock(ExpenseCategory::class);
		$existingTag = new ExpenseTag(
			'Old Name',
			$expenseCategory,
			null,
			['old.*'],
			new ImmutableDateTime('now'),
			false,
		);

		// Test data
		$tagId = 1;
		$newName = 'Updated Name';
		$newRegexes = ['updated.*', 'new.*'];
		$newIsTax = true;
		$now = new ImmutableDateTime('now');

		// Setup expectations
		$expenseTagRepositoryMock->shouldReceive('getById')
			->once()
			->with($tagId)
			->andReturn($existingTag);

		$datetimeFactoryMock->shouldReceive('createNow')->andReturn($now);
		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$updatedTag = $facade->update($tagId, $newName, $newRegexes, $newIsTax);

		// Validate
		$this->assertEquals($newName, $updatedTag->getName());
		$this->assertEquals($newRegexes, $updatedTag->getRegexes());
		$this->assertTrue($updatedTag->isTax());
	}

	public function testManuallySetMainTag(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Test data
		$expenseId = Uuid::uuid4();
		$tagId = 5;

		$bankExpense = Mockery::mock(BankExpense::class);
		$expenseTag = Mockery::mock(ExpenseTag::class);

		// Setup expectations
		$bankExpenseRepositoryMock->shouldReceive('getById')
			->once()
			->with($expenseId)
			->andReturn($bankExpense);

		$expenseTagRepositoryMock->shouldReceive('getById')
			->once()
			->with($tagId)
			->andReturn($expenseTag);

		$bankExpense->shouldReceive('setManuallyMainTag')
			->once()
			->with($expenseTag);

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->manuallySetMainTag($expenseId, $tagId);

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

	public function testManuallySetOtherTag(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Test data
		$expenseId = Uuid::uuid4();
		$tagId = 3;

		$bankExpense = Mockery::mock(BankExpense::class);
		$expenseTag = Mockery::mock(ExpenseTag::class);

		// Setup expectations
		$bankExpenseRepositoryMock->shouldReceive('getById')
			->once()
			->with($expenseId)
			->andReturn($bankExpense);

		$expenseTagRepositoryMock->shouldReceive('getById')
			->once()
			->with($tagId)
			->andReturn($expenseTag);

		$bankExpense->shouldReceive('addManuallyOtherTag')
			->once()
			->with($expenseTag);

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->manuallySetOtherTag($expenseId, $tagId);

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

	public function testManuallyRemoveOtherTag(): void
	{
		// Mock dependencies
		$expenseTagRepositoryMock = Mockery::mock(ExpenseTagRepository::class);
		$expenseCategoryRepositoryMock = Mockery::mock(ExpenseCategoryRepository::class);
		$bankExpenseRepositoryMock = Mockery::mock(BankExpenseRepository::class);
		$datetimeFactoryMock = Mockery::mock(DatetimeFactory::class);
		$entityManagerMock = Mockery::mock(EntityManagerInterface::class);
		$consoleOutputHelperMock = Mockery::mock(ConsoleCurrentOutputHelper::class);
		$systemValueFacadeMock = Mockery::mock(SystemValueFacade::class);

		// Create facade
		$facade = new ExpenseTagFacade(
			$expenseTagRepositoryMock,
			$expenseCategoryRepositoryMock,
			$bankExpenseRepositoryMock,
			$datetimeFactoryMock,
			$entityManagerMock,
			$consoleOutputHelperMock,
			$systemValueFacadeMock,
		);

		// Test data
		$expenseId = Uuid::uuid4();
		$tagId = 7;

		$bankExpense = Mockery::mock(BankExpense::class);
		$expenseTag = Mockery::mock(ExpenseTag::class);

		// Setup expectations
		$bankExpenseRepositoryMock->shouldReceive('getById')
			->once()
			->with($expenseId)
			->andReturn($bankExpense);

		$expenseTagRepositoryMock->shouldReceive('getById')
			->once()
			->with($tagId)
			->andReturn($expenseTag);

		$bankExpense->shouldReceive('manuallyRemoveOtherTag')
			->once()
			->with($expenseTag);

		$entityManagerMock->shouldReceive('flush')->once();

		// Execute
		$facade->manuallyRemoveOtherTag($expenseId, $tagId);

		// Mockery will verify expectations
		$this->assertTrue(true);
	}

}
