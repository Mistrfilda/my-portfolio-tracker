<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockClosedPosition>
 */
class StockClosedPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id): StockClosedPosition
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockClosedPosition');
		$qb->where($qb->expr()->eq('stockClosedPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockClosedPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockClosedPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockClosedPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockClosedPosition');
		$qb->andWhere($qb->expr()->in('stockClosedPosition.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockClosedPosition');
	}

}
