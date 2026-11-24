<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<TaxYearReport>
 */
class TaxYearReportRepository extends BaseRepository
{

	public function getById(UuidInterface $id, AppAdmin|null $appAdmin = null): TaxYearReport
	{
		return $this->loadById($id, $this->createQueryBuilder($appAdmin));
	}

	public function getByIdForProcessing(UuidInterface $id): TaxYearReport
	{
		return $this->loadById($id, $this->doctrineRepository->createQueryBuilder('taxYearReport'));
	}

	public function findLatestForYear(int $year, AppAdmin|null $appAdmin = null): TaxYearReport|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('taxYearReport.year', ':year'));
		$qb->setParameter('year', $year);
		$qb->orderBy('taxYearReport.createdAt', 'DESC');
		$qb->setMaxResults(1);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearReport);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function findLatestCompletedForYear(int $year, AppAdmin|null $appAdmin = null): TaxYearReport|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->eq('taxYearReport.year', ':year'),
			$qb->expr()->eq('taxYearReport.status', ':status'),
		);
		$qb->setParameter('year', $year);
		$qb->setParameter('status', TaxYearReportStatusEnum::COMPLETED);
		$qb->orderBy('taxYearReport.createdAt', 'DESC');
		$qb->setMaxResults(1);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearReport);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function findLatestPendingForYear(int $year, AppAdmin|null $appAdmin = null): TaxYearReport|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->eq('taxYearReport.year', ':year'),
			$qb->expr()->in('taxYearReport.status', ':statuses'),
		);
		$qb->setParameter('year', $year);
		$qb->setParameter('statuses', [
			TaxYearReportStatusEnum::QUEUED,
			TaxYearReportStatusEnum::PROCESSING,
		]);
		$qb->orderBy('taxYearReport.createdAt', 'DESC');
		$qb->setMaxResults(1);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearReport);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<TaxYearReport>
	 */
	public function findRecentForYear(int $year, int $limit = 10, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('taxYearReport.year', ':year'));
		$qb->setParameter('year', $year);
		$qb->orderBy('taxYearReport.createdAt', 'DESC');
		$qb->setMaxResults($limit);

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('taxYearReport');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'taxYearReport',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);

		return $qb;
	}

	private function loadById(UuidInterface $id, QueryBuilder $qb): TaxYearReport
	{
		$qb->andWhere($qb->expr()->eq('taxYearReport.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearReport);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

}
