<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Bank;

use App\Cash\Expense\Category\ExpenseCategory;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<BankExpense>
 */
class BankExpenseRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): BankExpense
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankExpense');
		$qb->where($qb->expr()->eq('bankExpense.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankExpense);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	public function findByIdentifier(string $identifier, LockModeEnum|null $lockMode = null): BankExpense|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankExpense');
		$qb->where($qb->expr()->eq('bankExpense.identifier', ':identifier'));
		$qb->setParameter('identifier', $identifier);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankExpense);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<BankExpense>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<BankExpense>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('bankExpense');
		$qb->andWhere($qb->expr()->in('bankExpense.id', $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<BankExpense>
	 */
	public function findByTagCategory(
		ExpenseCategory $expenseCategory,
		int|null $year,
		int|null $month,
	): array
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('bankExpense.mainTag', 'tag');

		$qb->andWhere(
			$qb->expr()->eq('tag.expenseCategory', ':expenseCategory'),
		);
		$qb->setParameter('expenseCategory', $expenseCategory);

		if ($month !== null) {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('MONTH(bankExpense.settlementDate)', ':month'),
					$qb->expr()->eq('MONTH(bankExpense.transactionDate)', ':month'),
				),
			);
			$qb->setParameter('month', $month);
		}

		if ($year !== null) {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('YEAR(bankExpense.settlementDate)', ':year'),
					$qb->expr()->eq('YEAR(bankExpense.transactionDate)', ':year'),
				),
			);
			$qb->setParameter('year', $year);
		}

		/** @phpstan-ignore-next-line  */
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankExpense');
		$qb->leftJoin('bankExpense.mainTag', 'mainTag');
		return $qb;
	}

}
