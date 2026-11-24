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
 * @extends BaseRepository<TaxYearDonation>
 */
class TaxYearDonationRepository extends BaseRepository
{

	public function getById(UuidInterface $id, AppAdmin|null $appAdmin = null): TaxYearDonation
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('taxYearDonation.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof TaxYearDonation);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<TaxYearDonation>
	 */
	public function findByProfile(TaxYearProfile $profile, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin ?? $profile->getAppAdmin());
		$qb->andWhere($qb->expr()->eq('taxYearDonation.taxYearProfile', ':profile'));
		$qb->setParameter('profile', $profile);
		$qb->orderBy('taxYearDonation.donatedAt', 'ASC');
		$qb->addOrderBy('taxYearDonation.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('taxYearDonation');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'taxYearDonation',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);

		return $qb;
	}

}
