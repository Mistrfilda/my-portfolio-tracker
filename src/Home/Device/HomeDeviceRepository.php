<?php

declare(strict_types = 1);

namespace App\Home\Device;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Home\Home;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<HomeDevice>
 */
class HomeDeviceRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): HomeDevice
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): HomeDevice
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('homeDevice.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof HomeDevice);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function getByIdForHome(UuidInterface $id, Home $home): HomeDevice
	{
		$device = $this->getById($id);
		if (!$device->getHome()->getId()->equals($home->getId())) {
			throw new NoEntityFoundException();
		}

		return $device;
	}

	public function getByInternalId(string $internalId): HomeDevice
	{
		// Keep the existing sensor ingestion contract until the separate API ownership migration.
		$qb = $this->doctrineRepository->createQueryBuilder('homeDevice');
		$qb->where($qb->expr()->eq('homeDevice.internalId', ':internalId'));
		$qb->setParameter('internalId', $internalId);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof HomeDevice);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByInternalId(string $internalId): HomeDevice|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('homeDevice');
		$qb->where($qb->expr()->eq('homeDevice.internalId', ':internalId'));
		$qb->setParameter('internalId', $internalId);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof HomeDevice);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<HomeDevice>
	 */
	public function findAll(): array
	{
		// Used by the existing sensor API; web lists use createQueryBuilderForHome().
		return $this->doctrineRepository->findAll();
	}

	/** @return array<HomeDevice> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('homeDevice.home', 'ownerHome');
		$this->addAppAdminToQueryBuilder($qb, 'ownerHome', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('homeDevice');
	}

	public function createQueryBuilderForHome(Home $home): QueryBuilder
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->eq('homeDevice.home', ':home'));
		$qb->setParameter('home', $home);

		return $qb;
	}

}
