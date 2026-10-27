<?php

declare(strict_types = 1);

namespace App\Work\Report;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/** @extends BaseRepository<WorkReport> */
class WorkReportRepository extends BaseRepository
{

	public function findForCurrentAppAdmin(UuidInterface $id): WorkReport|null
	{
		$result = $this->createQueryBuilder()
			->andWhere('workReport.id = :id')
			->setParameter('id', $id->toString())
			->getQuery()
			->getOneOrNullResult();

		assert($result === null || $result instanceof WorkReport);
		return $result;
	}

	public function getByReportDateForAppAdmin(ImmutableDateTime $date, AppAdmin $appAdmin): WorkReport|null
	{
		$result = $this->createQueryBuilder($appAdmin)
			->andWhere('workReport.reportDate = :date')
			->setParameter('date', $date, Types::DATE_IMMUTABLE)
			->getQuery()
			->getOneOrNullResult();

		assert($result === null || $result instanceof WorkReport);
		return $result;
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('workReport');
		$this->addAppAdminToQueryBuilder($qb, 'workReport', $appAdmin ?? $this->currentAppAdminGetter->getAppAdmin());
		return $qb;
	}

}
