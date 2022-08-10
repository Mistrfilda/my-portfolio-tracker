<?php

declare(strict_types = 1);

namespace App\Stock\Price;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<StockAssetPriceRecord>
 */
class StockAssetPriceRecordRepository extends BaseRepository
{

	public function getById(int $id, LockModeEnum|null $lockMode = null): StockAssetPriceRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetPriceRecord');
		$qb->where($qb->expr()->eq('stockAssetPriceRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByStockAssetAndDate(StockAsset $stockAsset, ImmutableDateTime $date): StockAssetPriceRecord|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetPriceRecord');

		$qb->andWhere(
			$qb->expr()->eq('stockAssetPriceRecord.date', ':date'),
			$qb->expr()->eq('stockAssetPriceRecord.stockAsset', ':stockAsset'),
		);

		$qb->setParameter('date', $date);
		$qb->setParameter('stockAsset', $stockAsset);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<StockAssetPriceRecord>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<StockAssetPriceRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetPriceRecord');
		$qb->andWhere($qb->expr()->in('stockAssetPriceRecord.id', $ids));

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAssetPriceRecord');
	}

}
