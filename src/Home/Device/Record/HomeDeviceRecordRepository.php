<?php

declare(strict_types = 1);

namespace App\Home\Device\Record;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Home\Device\HomeDevice;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<HomeDeviceRecord>
 */
class HomeDeviceRecordRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): HomeDeviceRecord
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): HomeDeviceRecord
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('homeDeviceRecord.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof HomeDeviceRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<HomeDeviceRecord>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilderForCurrentAppAdmin()->getQuery()->getResult();
	}

	/** @return array<HomeDeviceRecord> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('homeDeviceRecord.homeDevice', 'ownerDevice');
		$qb->innerJoin('ownerDevice.home', 'ownerHome');
		$this->addAppAdminToQueryBuilder($qb, 'ownerHome', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('homeDeviceRecord');
	}

	public function createQueryBuilderForDevice(HomeDevice $homeDevice): QueryBuilder
	{
		return $this->createQueryBuilderForDeviceForAppAdmin($homeDevice, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilderForDeviceForAppAdmin(HomeDevice $homeDevice, AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('homeDeviceRecord.homeDevice', ':homeDevice'));
		$qb->setParameter('homeDevice', $homeDevice);
		$qb->orderBy('homeDeviceRecord.createdAt', 'DESC');

		return $qb;
	}

	/**
	 * @return array<HomeDeviceRecord>
	 */
	public function findLatestForDevice(HomeDevice $homeDevice, int $limit): array
	{
		// The sensor API supplies a selected device; web callers first load it through its owner's home.
		$qb = $this->createQueryBuilderForDeviceForAppAdmin($homeDevice, $homeDevice->getHome()->getAppAdmin());
		$qb->setMaxResults($limit);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<HomeDeviceRecord>
	 */
	public function findForDeviceSince(
		HomeDevice $homeDevice,
		ImmutableDateTime $since,
	): array
	{
		$qb = $this->createQueryBuilderForDevice($homeDevice);
		$qb->andWhere($qb->expr()->gte('homeDeviceRecord.createdAt', ':since'));
		$qb->setParameter('since', $since);
		$qb->orderBy('homeDeviceRecord.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

}
