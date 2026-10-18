<?php

declare(strict_types = 1);

namespace App\Stock\Position\Closed;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockClosedPosition>
 */
class StockClosedPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id): StockClosedPosition
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function getByIdForAppAdmin(UuidInterface $id, AppAdmin $appAdmin): StockClosedPosition
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('stockClosedPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockClosedPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/** @return array<StockClosedPosition> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	/**
	 * @return array<StockClosedPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface|string> $ids
	 * @return array<StockClosedPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
		$qb->andWhere($qb->expr()->in('stockClosedPosition.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockClosedPosition>
	 */
	public function findBetweenDates(ImmutableDateTime $start, ImmutableDateTime $end): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockClosedPosition');
		$qb->andWhere(
			$qb->expr()->gte('stockClosedPosition.orderDate', ':start'),
			$qb->expr()->lte('stockClosedPosition.orderDate', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('stockClosedPosition.orderDate', 'ASC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('stockClosedPosition.stockPosition', 'stockPosition');
		$this->addAppAdminToQueryBuilder($qb, 'stockPosition', $appAdmin);

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockClosedPosition');
	}

}
