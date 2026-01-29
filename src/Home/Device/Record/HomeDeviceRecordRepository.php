<?php

declare(strict_types = 1);

namespace App\Home\Device\Record;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Home\Device\HomeDevice;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<HomeDeviceRecord>
 */
class HomeDeviceRecordRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): HomeDeviceRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('homeDeviceRecord');
		$qb->where($qb->expr()->eq('homeDeviceRecord.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
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
		return $this->doctrineRepository->findAll();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('homeDeviceRecord');
	}

	public function createQueryBuilderForDevice(HomeDevice $homeDevice): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('homeDeviceRecord');
		$qb->where($qb->expr()->eq('homeDeviceRecord.homeDevice', ':homeDevice'));
		$qb->setParameter('homeDevice', $homeDevice);
		$qb->orderBy('homeDeviceRecord.createdAt', 'DESC');

		return $qb;
	}

}
