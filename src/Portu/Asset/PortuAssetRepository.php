<?php

declare(strict_types = 1);

namespace App\Portu\Asset;

use App\Asset\Asset;
use App\Asset\AssetRepository;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortuAsset>
 */
class PortuAssetRepository extends BaseRepository implements AssetRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortuAsset
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portuAsset');
		$qb->where($qb->expr()->eq('portuAsset.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortuAsset);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<Asset>
	 */
	public function getAllActiveAssets(): array
	{
		return $this->findAll();
	}

	/**
	 * @return array<PortuAsset>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<PortuAsset>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('portuAsset');
		$qb->andWhere($qb->expr()->in('portuAsset.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portuAsset');
	}

}
