<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<WorkMonthlyIncome>
 */
class WorkMonthlyIncomeRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): WorkMonthlyIncome
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): WorkMonthlyIncome
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('workMonthlyIncome.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof WorkMonthlyIncome);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<WorkMonthlyIncome>
	 */
	public function findAll(int $year): array
	{
		return $this->findAllForAppAdmin($this->currentAppAdminGetter->getAppAdmin(), $year);
	}

	/** @return array<WorkMonthlyIncome> */
	public function findAllForAppAdmin(AppAdmin $appAdmin, int $year): array
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('workMonthlyIncome.year', ':year'));
		$qb->setParameter('year', $year);
		$qb->orderBy('workMonthlyIncome.month', 'DESC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<WorkMonthlyIncome>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('workMonthlyIncome.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		return $qb->getQuery()->getResult();
	}

	public function getByYearAndMonth(int $year, int $month): WorkMonthlyIncome|null
	{
		return $this->getByYearAndMonthForAppAdmin($year, $month, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function getByYearAndMonthForAppAdmin(int $year, int $month, AppAdmin $appAdmin): WorkMonthlyIncome|null
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere(
			$qb->expr()->eq('workMonthlyIncome.year', ':year'),
			$qb->expr()->eq('workMonthlyIncome.month', ':month'),
		);

		$qb->setParameter('year', $year);
		$qb->setParameter('month', $month);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof WorkMonthlyIncome);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<WorkMonthlyIncome>
	 */
	public function findByYearAndMonth(int|null $year, int|null $month): array
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();

		if ($year !== null) {
			$qb->andWhere(
				$qb->expr()->eq('workMonthlyIncome.year', ':year'),
			);

			$qb->setParameter('year', $year);
		}

		if ($month !== null) {
			$qb->andWhere(
				$qb->expr()->eq('workMonthlyIncome.month', ':month'),
			);

			$qb->setParameter('month', $month);
		}

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'workMonthlyIncome', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('workMonthlyIncome');
	}

}
