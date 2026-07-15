<?php

declare(strict_types = 1);

namespace App\Portu\Price;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use App\Portu\Position\PortuPosition;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortuAssetPriceRecord>
 */
class PortuAssetPriceRecordRepository extends BaseRepository
{

	public function findFirstInPeriod(
		PortuPosition $portuPosition,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortuAssetPriceRecord|null
	{
		return $this->findPeriodBoundary($portuPosition, $start, $end, OrderBy::ASC);
	}

	public function findLastInPeriod(
		PortuPosition $portuPosition,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
	): PortuAssetPriceRecord|null
	{
		return $this->findPeriodBoundary($portuPosition, $start, $end, OrderBy::DESC);
	}

	public function getById(int $id, LockModeEnum|null $lockMode = null): PortuAssetPriceRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portuAssetPriceRecord');
		$qb->where($qb->expr()->eq('portuAssetPriceRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortuAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByPositionAndDate(
		ImmutableDateTime $date,
		PortuPosition $portuPosition,
	): PortuAssetPriceRecord|null
	{
		$qb = $this->createQueryBuilder();

		$qb->andWhere(
			$qb->expr()->eq('portuAssetPriceRecord.date', ':date'),
			$qb->expr()->eq('portuAssetPriceRecord.portuPosition', ':portuPosition'),
		);

		$qb->setParameter('date', $date);
		$qb->setParameter('portuPosition', $portuPosition);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof PortuAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<PortuAssetPriceRecord>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<PortuAssetPriceRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('portuAssetPriceRecord');
		$qb->andWhere($qb->expr()->in('portuAssetPriceRecord.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForDatagrid(UuidInterface $portuPositionId): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('portuAssetPriceRecord.portuPosition', ':position'));
		$qb->setParameter('position', $portuPositionId);

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portuAssetPriceRecord');
	}

	private function findPeriodBoundary(
		PortuPosition $portuPosition,
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		OrderBy $orderBy,
	): PortuAssetPriceRecord|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('portuAssetPriceRecord.portuPosition', ':portuPosition'),
			$qb->expr()->gte('portuAssetPriceRecord.date', ':start'),
			$qb->expr()->lte('portuAssetPriceRecord.date', ':end'),
		);
		$qb->setParameter('portuPosition', $portuPosition);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('portuAssetPriceRecord.date', $orderBy->value);
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortuAssetPriceRecord);
		return $result;
	}

}
