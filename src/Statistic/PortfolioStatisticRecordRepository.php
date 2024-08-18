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
		// Dotaz na minimální datum v měsíci
		$minDatesQb = $this->doctrineRepository->createQueryBuilder('p')
			->select('MIN(p.createdAt) as minDate, YEAR(p.createdAt) as eventYear, MONTH(p.createdAt) as eventMonth')
			->where('YEAR(p.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$maxDatesQb = $this->doctrineRepository->createQueryBuilder('p')
			->select('MAX(p.createdAt) as maxDate, YEAR(p.createdAt) as eventYear, MONTH(p.createdAt) as eventMonth')
			->where('YEAR(p.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$minDates = array_map(static fn ($result) => $result['minDate'], $minDatesQb->getQuery()->getResult());

		$maxDates = array_map(static fn ($result) => $result['maxDate'], $maxDatesQb->getQuery()->getResult());

		$qb = $this->doctrineRepository->createQueryBuilder('p');
		$qb->where($qb->expr()->in('p.createdAt', ':dates'))
			->setParameter('dates', array_merge($minDates, $maxDates))
			->orderBy('p.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

}
