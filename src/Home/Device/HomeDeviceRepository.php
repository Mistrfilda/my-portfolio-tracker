<?php

declare(strict_types = 1);

namespace App\Home\Device;

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
		$qb = $this->doctrineRepository->createQueryBuilder('homeDevice');
		$qb->where($qb->expr()->eq('homeDevice.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof HomeDevice);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function getByInternalId(string $internalId): HomeDevice
	{
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
		return $this->doctrineRepository->findAll();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('homeDevice');
	}

	public function createQueryBuilderForHome(Home $home): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('homeDevice');
		$qb->where($qb->expr()->eq('homeDevice.home', ':home'));
		$qb->setParameter('home', $home);

		return $qb;
	}

}
