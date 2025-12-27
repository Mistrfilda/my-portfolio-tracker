<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use App\Stock\Valuation\StockValuationTypeEnum;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockValuationData>
 */
class StockValuationDataRepository extends BaseRepository
{

	public function getById(UuidInterface $id, int|null $lockMode = null): StockValuationData
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockValuationData');
		$qb->where($qb->expr()->eq('stockValuationData.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockValuationData);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<string, StockValuationData>
	 */
	public function findLatestForStockAsset(StockAsset $stockAsset): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockValuationData', 'stockValuationData.valuationType');
		$qb->andWhere($qb->expr()->eq('stockValuationData.stockAsset', ':stockAsset'));
		$qb->setParameter('stockAsset', $stockAsset);
		$qb->andWhere($qb->expr()->eq('stockValuationData.lastActive', ':lastActive'));
		$qb->setParameter('lastActive', true);
		$qb->groupBy('stockValuationData.valuationType');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<StockValuationTypeEnum> $types
	 * @return array<string, StockValuationData>
	 */
	public function findTypesLatestForStockAsset(StockAsset $stockAsset, array $types): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockValuationData', 'stockValuationData.valuationType');
		$qb->andWhere($qb->expr()->eq('stockValuationData.stockAsset', ':stockAsset'));
		$qb->setParameter('stockAsset', $stockAsset);
		$qb->andWhere($qb->expr()->eq('stockValuationData.lastActive', ':lastActive'));
		$qb->setParameter('lastActive', true);
		$typeValues = array_map(static fn (StockValuationTypeEnum $type) => $type->value, $types);
		$qb->andWhere($qb->expr()->in('stockValuationData.valuationType', ':types'));
		$qb->setParameter('types', $typeValues);

		$qb->groupBy('stockValuationData.valuationType');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockValuationData>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockValuationData>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockValuationData');
		$qb->andWhere($qb->expr()->in('stockValuationData.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockValuationData');
	}

	public function updateLastActive(StockAsset $stockAsset): void
	{
		$qb = $this->createQueryBuilder();
		$qb->update();
		$qb->set('stockValuationData.lastActive', ':lastActive');
		$qb->setParameter('lastActive', false);
		$qb->andWhere($qb->expr()->eq('stockValuationData.stockAsset', ':stockAsset'));
		$qb->setParameter('stockAsset', $stockAsset);
		$qb->getQuery()->execute();
	}

	public function removeTodayData(StockAsset $stockAsset, ImmutableDateTime $now): void
	{
		$qb = $this->createQueryBuilder();
		$qb->delete();
		$qb->andWhere($qb->expr()->eq('stockValuationData.stockAsset', ':stockAsset'));
		$qb->setParameter('stockAsset', $stockAsset);
		$qb->andWhere($qb->expr()->eq('DATE(stockValuationData.parsedAt)', ':date'));
		$qb->setParameter('date', $now->format('Y-m-d'));
		$qb->getQuery()->execute();
	}

}
