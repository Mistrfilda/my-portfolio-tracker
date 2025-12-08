<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<CryptoClosedPosition>
 */
class CryptoClosedPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id): CryptoClosedPosition
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoClosedPosition');
		$qb->where($qb->expr()->eq('cryptoClosedPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof CryptoClosedPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<CryptoClosedPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<CryptoClosedPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('cryptoClosedPosition');
		$qb->andWhere($qb->expr()->in('cryptoClosedPosition.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('cryptoClosedPosition');
	}

}
