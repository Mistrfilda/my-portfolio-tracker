<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag;

use App\Cash\Expense\Category\ExpenseCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class ExpenseTagFacade
{

	public function __construct(
		private ExpenseTagRepository $expenseTagRepository,
		private ExpenseCategoryRepository $expenseCategoryRepository,
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

}
