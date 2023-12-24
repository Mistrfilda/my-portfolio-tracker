<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Dividend\StockAssetDividend;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
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

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$qb->innerJoin('stockAssetDividendRecord.stockAssetDividend', 'stockAssetDividend');
		return $qb;
	}

}
