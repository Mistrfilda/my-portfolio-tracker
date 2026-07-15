<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
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
	 * @return array<CryptoClosedPosition>
	 */
	public function findBetweenDates(ImmutableDateTime $start, ImmutableDateTime $end): array
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->gte('cryptoClosedPosition.orderDate', ':start'),
			$qb->expr()->lte('cryptoClosedPosition.orderDate', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('cryptoClosedPosition.orderDate', 'ASC');

		return $qb->getQuery()->getResult();
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
