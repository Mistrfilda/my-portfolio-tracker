<?php

declare(strict_types = 1);

namespace App\Test\Unit\Cash\Expense\UI\Control;

use App\Admin\AppAdmin;
use App\Cash\Bank\Account\BankAccount;
use App\Cash\Bank\BankSourceEnum;
use App\Cash\Bank\BankTransactionType;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Category\ExpenseCategory;
use App\Cash\Expense\Category\ExpenseCategoryEnum;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Cash\Expense\UI\Control\ExpenseOverviewDataSet;
use App\Currency\CurrencyEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class ExpenseOverviewDataSetTest extends TestCase
{

	public function testExtraTagsOverlapWithoutDuplicatingExpensesAndFilterAcrossCategories(): void
	{
		$firstCategory = new ExpenseCategory(
			'Shop',
			ExpenseCategoryEnum::ESHOP_OTHER,
			new ImmutableDateTime('2026-01-01'),
		);
		$secondCategory = new ExpenseCategory(
			'Food',
			ExpenseCategoryEnum::RESTAURANT,
			new ImmutableDateTime('2026-01-01'),
		);
		(new ReflectionProperty(ExpenseCategory::class, 'id'))->setValue($firstCategory, 4);
		(new ReflectionProperty(ExpenseCategory::class, 'id'))->setValue($secondCategory, 5);
		$firstMain = $this->tag(1, $firstCategory);
		$secondMain = $this->tag(2, $secondCategory);
		$mother = $this->tag(3);
		$gift = $this->tag(4);
		$unused = $this->tag(5);
		$first = $this->expense(-100.0, $firstMain, [$mother, $gift]);
		$second = $this->expense(-50.0, $secondMain, [$mother]);
		$untagged = $this->expense(-25.0, null, [$gift]);

		$all = new ExpenseOverviewDataSet([$first, $second, $untagged]);
		self::assertSame(-175.0, $all->selectedSummaryPrice->getPrice());
		self::assertSame(3, $all->selectedSummaryPrice->getCounter());
		self::assertCount(3, $all->groups);
		self::assertSame('Bez hlavního tagu', $all->groups[2]->getCategoryName());
		self::assertSame(-150.0, $all->otherTags[0]->summaryPrice->getPrice());
		self::assertSame(-125.0, $all->otherTags[1]->summaryPrice->getPrice());

		$filtered = new ExpenseOverviewDataSet([$first, $second, $untagged], $mother);
		self::assertCount(2, $filtered->groups);
		self::assertSame(-150.0, $filtered->selectedSummaryPrice->getPrice());
		self::assertSame(2, $filtered->selectedSummaryPrice->getCounter());
		self::assertSame(-125.0, $filtered->otherTags[1]->summaryPrice->getPrice());

		$empty = new ExpenseOverviewDataSet([$first, $second, $untagged], $unused);
		self::assertSame([], $empty->groups);
		self::assertSame(0, $empty->selectedSummaryPrice->getCounter());
		self::assertCount(2, $empty->otherTags);
	}

	private function tag(int $id, ExpenseCategory|null $category = null): ExpenseTag
	{
		$tag = new ExpenseTag(
			$this->createStub(AppAdmin::class),
			'Tag ' . $id,
			$category,
			null,
			[],
			new ImmutableDateTime('2026-01-01'),
			false,
		);
		(new ReflectionProperty(ExpenseTag::class, 'id'))->setValue($tag, $id);
		return $tag;
	}

	/** @param array<ExpenseTag> $extras */
	private function expense(float $amount, ExpenseTag|null $main, array $extras): BankExpense
	{
		$expense = new BankExpense(
			'test',
			BankSourceEnum::KOMERCNI_BANKA,
			BankTransactionType::CARD_PAYMENT,
			$amount,
			CurrencyEnum::CZK,
			new ImmutableDateTime('2026-01-01'),
			null,
			'test',
			new ImmutableDateTime('2026-01-01'),
			$this->createStub(BankAccount::class),
		);
		if ($main !== null) {
			$expense->setMainTag($main);
		}

		foreach ($extras as $extra) {
			$expense->addOtherTag($extra);
		}

		return $expense;
	}

}
