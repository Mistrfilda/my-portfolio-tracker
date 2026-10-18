<?php

declare(strict_types = 1);

namespace App\Crypto\Position\Closed;

use App\Admin\AppAdmin;
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
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function getByIdForAppAdmin(UuidInterface $id, AppAdmin $appAdmin): CryptoClosedPosition
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('cryptoClosedPosition.id', ':id'));
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
	 * @param array<UuidInterface|string> $ids
	 * @return array<CryptoClosedPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
		$qb->andWhere($qb->expr()->in('cryptoClosedPosition.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('cryptoClosedPosition.cryptoPosition', 'cryptoPosition');
		$this->addAppAdminToQueryBuilder($qb, 'cryptoPosition', $appAdmin);

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('cryptoClosedPosition');
	}

}
