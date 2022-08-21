<?php

declare(strict_types = 1);

namespace App\Portu\Position;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortuPosition>
 */
class PortuPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortuPosition
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portuPosition');
		$qb->where($qb->expr()->eq('portuPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortuPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<PortuPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<PortuPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('portuPosition');
		$qb->andWhere($qb->expr()->in('portuPosition.id', $ids));

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
	}

	/**
	 * @return array<PortuPosition>
	 */
	public function findAllOpened(): array
	{
		return $this->findAll();
	}

	public function createQueryBuilderForDatagrid(UuidInterface $portAssetId): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('portuPosition.portuAsset', 'portuAsset');
		$qb->andWhere($qb->expr()->eq('portuAsset.id', ':portuAssetId'));
		$qb->setParameter('portuAssetId', $portAssetId);

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portuPosition');
	}

}
