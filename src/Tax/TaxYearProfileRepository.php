<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<TaxYearProfile>
 */
class TaxYearProfileRepository extends BaseRepository
{

	public function findByYear(int $year, AppAdmin|null $appAdmin = null): TaxYearProfile|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('taxYearProfile.year', ':year'));
		$qb->setParameter('year', $year);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearProfile);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<TaxYearProfile>
	 */
	public function findAll(AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->orderBy('taxYearProfile.year', 'DESC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('taxYearProfile');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'taxYearProfile',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);

		return $qb;
	}

}
