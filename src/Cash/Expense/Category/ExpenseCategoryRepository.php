<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Category;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<ExpenseCategory>
 */
class ExpenseCategoryRepository extends BaseRepository
{

	public function getById(int $id, LockModeEnum|null $lockMode = null): ExpenseCategory
	{
		$qb = $this->doctrineRepository->createQueryBuilder('expenseCategory');
		$qb->where($qb->expr()->eq('expenseCategory.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof ExpenseCategory);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<ExpenseCategory>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<ExpenseCategory>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('expenseCategory');
		$qb->andWhere($qb->expr()->in('expenseCategory.id', $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<int, string>
	 */
	public function findPairs(): array
	{
		$pairs = [];
		foreach ($this->findAll() as $expenseCategory) {
			$pairs[$expenseCategory->getId()] = $expenseCategory->getName();
		}

		return $pairs;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('expenseCategory');
	}

}
