<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

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
		$qb = $this->doctrineRepository->createQueryBuilder('workMonthlyIncome');
		$qb->where($qb->expr()->eq('workMonthlyIncome.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
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
		return $this->doctrineRepository->findBy(['year' => $year], ['month' => 'DESC']);
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

		$qb = $this->doctrineRepository->createQueryBuilder('workMonthlyIncome');
		$qb->andWhere($qb->expr()->in('workMonthlyIncome.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function findByYearAndMonth(int $year, int $month): WorkMonthlyIncome|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('workMonthlyIncome');
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

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('workMonthlyIncome');
	}

}
