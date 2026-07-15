<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortfolioPeriodStatistic>
 */
class PortfolioPeriodStatisticRepository extends BaseRepository
{

	public function getById(
		UuidInterface $id,
		LockModeEnum|null $lockMode = null,
	): PortfolioPeriodStatistic
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('portfolioPeriodStatistic.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortfolioPeriodStatistic);
			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioPeriodStatistic');
	}

}
