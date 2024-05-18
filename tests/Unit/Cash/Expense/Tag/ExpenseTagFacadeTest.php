<?php

declare(strict_types=1);

namespace App\Test\Unit\Cash\Expense\Tag;

use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\Tag\ExpenseTagException;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mockery;
use PHPUnit\Framework\TestCase;


class ExpenseTagFacadeTest extends TestCase
{
    private ExpenseTagFacade $expenseTagFacade;

    public function setUp(): void
    {
        $this->expenseTagFacade = new ExpenseTagFacade(
	        UpdatedTestCase::createMockWithIgnoreMethods(ExpenseTagRepository::class),
	        UpdatedTestCase::createMockWithIgnoreMethods(ExpenseCategoryRepository::class),
	        UpdatedTestCase::createMockWithIgnoreMethods(BankExpenseRepository::class),
	        UpdatedTestCase::createMockWithIgnoreMethods(DatetimeFactory::class),
	        UpdatedTestCase::createMockWithIgnoreMethods(EntityManagerInterface::class),
        );
    }

    public function testCreateWithNoTagsThrowsException(): void
    {
        $name = 'Name1';
        $expenseCategory = null;
        $parentTag = null;
        $regexes = ['Regex1'];

        $this->expectException(ExpenseTagException::class);
        $this->expectExceptionMessage('Expense category or parent tag must be filled');

        $this->expenseTagFacade->create($name, $expenseCategory, $parentTag, $regexes);
    }

    public function testCreateWithTags(): void
    {
        $name = 'Name1';
        $expenseCategory = 1;
        $parentTag = 2;
        $regexes = ['Regex1'];

        $this->expenseTagFacade->create($name, $expenseCategory, $parentTag, $regexes);

        $this->assertInstanceOf(ExpenseTag::class, $this->expenseTagFacade->create($name, $expenseCategory, $parentTag, $regexes));
    }
}
