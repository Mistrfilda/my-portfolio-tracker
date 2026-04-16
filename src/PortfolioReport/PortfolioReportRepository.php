<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortfolioReport>
 */
class PortfolioReportRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortfolioReport
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioReport');
		$qb->where($qb->expr()->eq('portfolioReport.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortfolioReport);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByPeriod(
		PortfolioReportPeriodTypeEnum $periodType,
		ImmutableDateTime $dateFrom,
		ImmutableDateTime $dateTo,
	): PortfolioReport|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('portfolioReport.periodType', ':periodType'),
			$qb->expr()->eq('portfolioReport.dateFrom', ':dateFrom'),
			$qb->expr()->eq('portfolioReport.dateTo', ':dateTo'),
		);
		$qb->setParameter('periodType', $periodType);
		$qb->setParameter('dateFrom', $dateFrom);
		$qb->setParameter('dateTo', $dateTo);

		$result = $qb->getQuery()->getOneOrNullResult();
		if ($result === null) {
			return null;
		}

		assert($result instanceof PortfolioReport);
		return $result;
	}

	/** @return array<PortfolioReport> */
	public function findAll(): array
	{
		$qb = $this->createQueryBuilder();
		$qb->orderBy('portfolioReport.dateFrom', 'DESC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioReport');
	}

}
