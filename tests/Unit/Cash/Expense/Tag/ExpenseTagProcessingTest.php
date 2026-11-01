<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Expense\Tag;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Admin\CurrentAppAdminGetter;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\Tag\ExpenseTagException;
use App\Cash\Expense\Tag\ExpenseTagFacade;
use App\Cash\Expense\Tag\ExpenseTagMatchException;
use App\Cash\Expense\Tag\ExpenseTagRepository;
use App\Currency\CurrencyEnum;
use App\System\SystemValueFacade;
use App\Utils\Console\ConsoleCurrentOutputHelper;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ExpenseTagProcessingTest extends TestCase
{

	public function testManualMainTagSurvivesRulesWithoutBecomingAnExtraTag(): void
	{
		$expense = $this->expense();
		$manual = $this->tag(1, true);
		$matched = $this->tag(2, true, ['SHOP']);
		$extra = $this->tag(3, false, ['SHOP']);
		$context = $this->tag(4, false);
		$expense->setManuallyMainTag($manual);
		$expense->addManuallyOtherTag($context);
		$facade = $this->facade($expense, [$matched, $extra]);
		$facade->processExpensesForAppAdmin($this->createStub(AppAdmin::class));
		$facade->processExpensesForAppAdmin($this->createStub(AppAdmin::class));

		self::assertSame($manual, $expense->getMainTag());
		self::assertSame([$context, $extra], $expense->getOtherTags());
		self::assertFalse($expense->isOtherTagSetManually());
		$expense->manuallyRemoveOtherTag($context);
		self::assertFalse($expense->isOtherTagSetManually());
	}

	public function testAutomaticMainAndExtraTagsAreIdempotent(): void
	{
		$expense = $this->expense();
		$main = $this->tag(1, true, ['SHOP']);
		$extra = $this->tag(2, false, ['SHOP']);
		$facade = $this->facade($expense, [$main, $extra]);
		$facade->processExpensesForAppAdmin($this->createStub(AppAdmin::class));
		$facade->processExpensesForAppAdmin($this->createStub(AppAdmin::class));
		self::assertSame($main, $expense->getMainTag());
		self::assertSame([$extra], $expense->getOtherTags());
		self::assertFalse($expense->isMainTagSetManually());
	}

	public function testAutomaticMainTagConflictIsReported(): void
	{
		$expense = $this->expense();
		$expense->setMainTag($this->tag(1, true));
		$facade = $this->facade($expense, [$this->tag(2, true, ['SHOP'])]);
		$this->expectException(ExpenseTagMatchException::class);
		$facade->processExpensesForAppAdmin($this->createStub(AppAdmin::class));
	}

	public function testContextTagDoesNotClearAnExistingManualExtraOverride(): void
	{
		$expense = $this->expense();
		$extra = $this->tag(1, false, ['SHOP']);
		$context = $this->tag(2, false);
		$expense->addManuallyOtherTag($extra);
		$expense->addManuallyOtherTag($context);
		$expense->manuallyRemoveOtherTag($context);
		self::assertTrue($expense->isOtherTagSetManually());
	}

	public function testMainTagCannotBeAddedAsAnExtraTag(): void
	{
		$this->expectException(ExpenseTagException::class);
		$this->expense()->addManuallyOtherTag($this->tag(1, true));
	}

	public function testStandaloneExtraTagCanBeCreated(): void
	{
		$tag = $this->facade($this->expense(), [])->create('Pro mámu', null, null, [], false);
		self::assertFalse($tag->isMainTag());
		self::assertNull($tag->getParentTag());
	}

	/** @param array<ExpenseTag> $tags */
	private function facade(BankExpense $expense, array $tags): ExpenseTagFacade
	{
		$tagRepository = $this->createStub(ExpenseTagRepository::class);
		$tagRepository->method('findAllForAppAdmin')->willReturn($tags);
		$expenseRepository = $this->createStub(BankExpenseRepository::class);
		$expenseRepository->method('findAllForAppAdmin')->willReturn([$expense]);
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn(new ImmutableDateTime('2026-11-01'));
		return new ExpenseTagFacade(
			$tagRepository,
			$this->createStub(ExpenseCategoryRepository::class),
			$expenseRepository,
			$datetimeFactory,
			$this->createStub(EntityManagerInterface::class),
			$this->createStub(ConsoleCurrentOutputHelper::class),
			$this->createStub(SystemValueFacade::class),
			$this->createStub(CurrentAppAdminGetter::class),
			$this->createStub(AppAdminRepository::class),
		);
	}

	/** @param array<string> $rules */
	private function tag(int $id, bool $main, array $rules = []): ExpenseTag
	{
		$tag = new ExpenseTag(
			$this->createStub(AppAdmin::class),
			'Tag ' . $id,
			$main ? $this->createStub(ExpenseCategory::class) : null,
			null,
			$rules,
			new ImmutableDateTime('2026-11-01'),
			false,
		);
		(new ReflectionProperty(ExpenseTag::class, 'id'))->setValue($tag, $id);
		return $tag;
	}

	private function expense(): BankExpense
	{
		return new BankExpense(
			'test',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			-100.0,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-10-31'),
			null,
			'VISA SHOP',
			new ImmutableDateTime('2026-11-01'),
			$this->createStub(BankAccount::class),
		);
	}

}
