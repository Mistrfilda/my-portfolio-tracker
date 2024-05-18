<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Doctrine\BaseRepository;
use App\Doctrine\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<PortfolioStatistic>
 */
class PortfolioStatisticRepository extends BaseRepository
{

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioStatistic');
	}

	/**
	 * @return array<PortfolioStatistic>
	 */
	public function getPortfolioTotalValueForType(PortolioStatisticType $type, int $currentDay): array
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('portfolioStatistic.portfolioStatisticRecord', 'record');
		$qb->andWhere(
			$qb->expr()->in('DAY(record.createdAt)', ':days'),
		);
		$qb->andWhere(
			$qb->expr()->eq('portfolioStatistic.type', ':type'),
		);
		$qb->setParameter('type', $type);
		$qb->setParameter('days', [1, 10, 20, 27, $currentDay]);
		$qb->orderBy('record.createdAt', OrderBy::ASC->value);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<PortfolioStatistic>
	 */
	public function getPortfolioTotalValueForTypeForGreaterDate(
		PortolioStatisticType $type,
		ImmutableDateTime $date,
	): array
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('portfolioStatistic.portfolioStatisticRecord', 'record');
		$qb->andWhere(
			$qb->expr()->gte('record.createdAt', ':date'),
		);
		$qb->andWhere(
			$qb->expr()->eq('portfolioStatistic.type', ':type'),
		);
		$qb->setParameter('type', $type);
		$qb->setParameter('date', $date);
		$qb->orderBy('record.createdAt', OrderBy::ASC->value);
		return $qb->getQuery()->getResult();
	}

}
