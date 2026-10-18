<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Admin\AppAdmin;
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
	public function findAllOrdered(AppAdmin|null $appAdmin = null): array
	{
		return $this->createQueryBuilder($appAdmin)
			->orderBy('portfolioPerformanceMonth.periodEndAt', 'ASC')
			->getQuery()
			->getResult();
	}

	public function findLastEndingAtOrBefore(
		ImmutableDateTime $date,
		AppAdmin|null $appAdmin = null,
	): PortfolioPerformanceMonth|null
	{
		$result = $this->createQueryBuilder($appAdmin)
			->andWhere('portfolioPerformanceMonth.periodEndAt <= :date')
			->setParameter('date', $date)
			->orderBy('portfolioPerformanceMonth.periodEndAt', 'DESC')
			->setMaxResults(1)
			->getQuery()
			->getOneOrNullResult();

		assert($result === null || $result instanceof PortfolioPerformanceMonth);
		return $result;
	}

	public function deleteAll(AppAdmin $appAdmin): void
	{
		$this->createQueryBuilder($appAdmin)
			->delete()
			->getQuery()
			->execute();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioPerformanceMonth');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'portfolioPerformanceMonth',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb;
	}

}
