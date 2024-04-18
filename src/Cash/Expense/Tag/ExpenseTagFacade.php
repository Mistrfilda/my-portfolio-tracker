<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag;

use App\Cash\Expense\Bank\BankExpenseRepository;
use App\Cash\Expense\Category\ExpenseCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Symfony\Component\Console\Output\OutputInterface;

class ExpenseTagFacade
{

	public function __construct(
		private ExpenseTagRepository $expenseTagRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
		private BankExpenseRepository $bankExpenseRepository,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
	)
	{

	}

	/**
	 * @param array<string> $regexes
	 */
	public function create(
		string $name,
		int|null $expenseCategory,
		int|null $parentTag,
		array $regexes,
	): ExpenseTag
	{
		if ($expenseCategory === null && $parentTag === null) {
			throw new ExpenseTagException('Expense category or parent tag must be filled');
		}

		$now = $this->datetimeFactory->createNow();
		$expenseCategoryEntity = null;
		$parentTagEntity = null;

		if ($expenseCategory !== null) {
			$expenseCategoryEntity = $this->expenseCategoryRepository->getById($expenseCategory);
		}

		if ($parentTag !== null) {
			$parentTagEntity = $this->expenseTagRepository->getById($parentTag);
		}

		$expenseTag = new ExpenseTag($name, $expenseCategoryEntity, $parentTagEntity, $regexes, $now);
		$this->entityManager->persist($expenseTag);
		$this->entityManager->flush();
		$this->entityManager->refresh($expenseTag);

		return $expenseTag;
	}

	/**
	 * @param array<string> $regexes
	 */
	public function update(int $id, string $name, array $regexes): ExpenseTag
	{
		$expenseTag = $this->expenseTagRepository->getById($id);
		$expenseTag->update(
			$name,
			$regexes,
			$this->datetimeFactory->createNow(),
		);
		$this->entityManager->flush();

		return $expenseTag;
	}

	public function processExpenses(OutputInterface|null $output = null): void
	{
		$expenseTags = $this->expenseTagRepository->findAll();
		foreach ($this->bankExpenseRepository->findAll() as $bankExpense) {
			$bankExpenseRawContent = str_replace("\n", ' ', $bankExpense->getTransactionRawContent());
			foreach ($expenseTags as $expenseTag) {
				$matched = false;
				foreach ($expenseTag->getRegexes() as $regex) {
					$pattern = sprintf('~.*%s.*~', preg_quote($regex, '/'));
					if (preg_match($pattern, $bankExpenseRawContent) === 1) {
						$matched = true;
						break;
					}
				}

				if ($matched) {
					if ($expenseTag->isMainTag()) {
						if (
							$bankExpense->getMainTag() !== null
							&& $bankExpense->getMainTag()->getId() !== $expenseTag->getId()
						) {
							throw new ExpenseTagMatchException('Duplicate main bank expense tag');
						}

						$bankExpense->setMainTag($expenseTag);
					} else {
						$bankExpense->addOtherTag($expenseTag);
					}

					$this->entityManager->flush();
				}
			}
		}
	}

}
