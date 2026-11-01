<?php

declare(strict_types = 1);

namespace App\Cash\Expense\UI\Control;

use App\Asset\Price\SummaryPrice;
use App\Cash\Expense\Bank\BankExpense;
use App\Cash\Expense\Tag\ExpenseTag;
use App\Currency\CurrencyEnum;

class ExpenseOverviewDataSet
{

	/** @var array<ExpanseOverviewData> */
	public readonly array $groups;

	/** @var array<ExpenseOverviewTagSummary> */
	public readonly array $otherTags;

	public readonly SummaryPrice $selectedSummaryPrice;

	/** @param array<BankExpense> $expenses */
	public function __construct(array $expenses, ExpenseTag|null $selectedOtherTag = null)
	{
		$groups = [];
		$otherTags = [];
		$this->selectedSummaryPrice = new SummaryPrice(CurrencyEnum::CZK);
		foreach ($expenses as $expense) {
			$matches = $selectedOtherTag === null;
			foreach ($expense->getOtherTags() as $tag) {
				if ($tag->isMainTag()) {
					continue;
				}

				$otherTags[$tag->getId()] ??= new ExpenseOverviewTagSummary($tag, new SummaryPrice(CurrencyEnum::CZK));
				$otherTags[$tag->getId()]->summaryPrice->addBankExpense($expense);
				if ($tag->getId() === $selectedOtherTag?->getId()) {
					$matches = true;
				}
			}

			if ($matches) {
				$category = $expense->getMainTag()?->getExpenseCategory();
				$groups[$category?->getId() ?? 0][] = $expense;
				$this->selectedSummaryPrice->addBankExpense($expense);
			}
		}

		$data = [];
		foreach ($groups as $groupExpenses) {
			$summary = new SummaryPrice(CurrencyEnum::CZK);
			foreach ($groupExpenses as $expense) {
				$summary->addBankExpense($expense);
			}

			$data[] = new ExpanseOverviewData(
				$groupExpenses[0]->getMainTag()?->getExpenseCategory(),
				$groupExpenses,
				$summary,
			);
		}

		usort(
			$data,
			static fn (ExpanseOverviewData $a, ExpanseOverviewData $b): int =>
				$a->getSummaryPrice()->getPrice() <=> $b->getSummaryPrice()->getPrice(),
		);
		usort(
			$otherTags,
			static fn (ExpenseOverviewTagSummary $a, ExpenseOverviewTagSummary $b): int =>
				$a->summaryPrice->getPrice() <=> $b->summaryPrice->getPrice(),
		);
		$this->groups = $data;
		$this->otherTags = $otherTags;
	}

}
