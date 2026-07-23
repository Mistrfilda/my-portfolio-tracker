<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<PortfolioPerformanceMonth>
 */
class PortfolioPerformanceMonthRepository extends BaseRepository
{

	/**
	 * @return array<PortfolioPerformanceMonth>
	 */
	public function findAllOrdered(): array
	{
		return $this->createQueryBuilder()
			->orderBy('portfolioPerformanceMonth.periodEndAt', 'ASC')
			->getQuery()
			->getResult();
	}

	public function findLastEndingAtOrBefore(ImmutableDateTime $date): PortfolioPerformanceMonth|null
	{
		$result = $this->createQueryBuilder()
			->andWhere('portfolioPerformanceMonth.periodEndAt <= :date')
			->setParameter('date', $date)
			->orderBy('portfolioPerformanceMonth.periodEndAt', 'DESC')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();

		assert($result === null || $result instanceof PortfolioPerformanceMonth);
		return $result;
	}

	public function deleteAll(): void
	{
		$this->createQueryBuilder()
			->delete()
			->getQuery()
			->execute();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioPerformanceMonth');
	}

}
