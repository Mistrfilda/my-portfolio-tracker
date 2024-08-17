<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<PortfolioStatisticRecord>
 */
class PortfolioStatisticRecordRepository extends BaseRepository
{

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
	}

	public function getById(int $id, LockModeEnum|null $lockMode = null): PortfolioStatisticRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
		$qb->where($qb->expr()->eq('portfolioStatisticRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortfolioStatisticRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
		$qb->andWhere($qb->expr()->in('portfolioStatisticRecord.id', $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findMinMaxDateByMonth(int $year): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('p1');

		$subQueryMin = $this->doctrineRepository->createQueryBuilder('p2')
			->select('MIN(p2.createdAt)')
			->where('YEAR(p2.createdAt) = :year')
			->andWhere('MONTH(p2.createdAt) = MONTH(p1.createdAt)');

		$subQueryMax = $this->doctrineRepository->createQueryBuilder('p3')
			->select('MAX(p3.createdAt)')
			->where('YEAR(p3.createdAt) = :year')
			->andWhere('MONTH(p3.createdAt) = MONTH(p1.createdAt)');

		$qb->where('YEAR(p1.createdAt) = :year')
			->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('p1.createdAt', '(' . $subQueryMin->getDQL() . ')'),
					$qb->expr()->eq('p1.createdAt', '(' . $subQueryMax->getDQL() . ')'),
				),
			)
			->setParameter('year', $year)
			->orderBy('p1.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

}
