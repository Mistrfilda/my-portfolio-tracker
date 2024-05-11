<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\StockAssetDividend;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividendRecord>
 */
class StockAssetDividendRecordRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): StockAssetDividendRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$qb->where($qb->expr()->eq('stockAssetDividendRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetDividendRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findAllForMonthChart(): array
	{
		$qb = $this->createQueryBuilder();
		$qb->orderBy('stockAssetDividend.exDate', OrderBy::ASC->value);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$qb->andWhere($qb->expr()->in('stockAssetDividendRecord.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function findOneByStockDividend(StockAssetDividend $stockAssetDividend): StockAssetDividendRecord|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$qb->where($qb->expr()->eq('stockAssetDividendRecord.stockAssetDividend', ':stockAssetDividend'));
		$qb->setParameter('stockAssetDividend', $stockAssetDividend);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetDividendRecord);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByStockAssetSinceDate(StockAsset $stockAsset, ImmutableDateTime $date): array
	{
		$qb = $this->createQueryBuilder();

		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->gte('stockAssetDividend.exDate', ':exDate'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('exDate', $date);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByStockAssetForYear(StockAsset $stockAsset, int $year): array
	{
		$qb = $this->createQueryBuilder();

		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->eq('YEAR(stockAssetDividend.exDate)', ':year'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('year', $year);

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$qb->innerJoin('stockAssetDividendRecord.stockAssetDividend', 'stockAssetDividend');
		$qb->innerJoin('stockAssetDividend.stockAsset', 'stockAsset');
		return $qb;
	}

}
