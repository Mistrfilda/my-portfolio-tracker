<?php

declare(strict_types = 1);

namespace App\Cash\Expense\Tag;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<ExpenseTag>
 */
class ExpenseTagRepository extends BaseRepository
{

	public function getById(int $id, LockModeEnum|null $lockMode = null): ExpenseTag
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		int $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): ExpenseTag
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('expenseTag.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof ExpenseTag);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<ExpenseTag>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult();
	}

	/**
	 * @return array<ExpenseTag>
	 */
	public function findAllMain(): array
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->isNull('expenseTag.parentTag'));
		$qb->orderBy('expenseTag.name', 'ASC');

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<ExpenseTag>
	 */
	public function findAllOtherTags(): array
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->isNotNull('expenseTag.parentTag'));
		$qb->orderBy('expenseTag.name', 'ASC');

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<int> $ids
	 * @return array<ExpenseTag>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('expenseTag.id', ':ids'));
		$qb->setParameter('ids', $ids);

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

	/** @return array<ExpenseTag> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'expenseTag', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('expenseTag');
	}

}
