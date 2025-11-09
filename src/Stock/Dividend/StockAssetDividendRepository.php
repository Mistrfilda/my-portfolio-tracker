<?php

declare(strict_types = 1);

namespace App\Stock\Dividend;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Stock\Asset\StockAsset;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividend>
 */
class StockAssetDividendRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): StockAssetDividend
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->where($qb->expr()->eq('stockAssetDividend.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetDividend);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockAssetDividend>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAssetDividend>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->andWhere($qb->expr()->in('stockAssetDividend.id', $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<int, StockAssetDividend>
	 */
	public function findByStockAsset(StockAsset $stockAsset): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<int, StockAssetDividend>
	 */
	public function findByStockAssetSinceDate(StockAsset $stockAsset, ImmutableDateTime $date): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->gte('stockAssetDividend.exDate', ':exDate'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('exDate', $date);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<int, StockAssetDividend>
	 */
	public function findByStockAssetForYear(StockAsset $stockAsset, int $year): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->eq('YEAR(stockAssetDividend.exDate)', ':year'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('year', $year);

		$qb->orderBy('stockAssetDividend.exDate', 'ASC');

		return $qb->getQuery()->getResult();
	}

	public function findOneByStockAssetExDate(
		StockAsset $stockAsset,
		ImmutableDateTime $exDate,
	): StockAssetDividend|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.exDate', ':exDate'),
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
		);

		$qb->setParameter('exDate', $exDate);
		$qb->setParameter('stockAsset', $stockAsset);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetDividend);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function getCountSinceDate(ImmutableDateTime $date): int
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->gte('stockAssetDividend.exDate', ':exDate'),
		);

		$qb->setParameter('exDate', $date);

		$qb->select('count(stockAssetDividend.id)');

		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));
		return (int) $result;
	}

	/**
	 * @return array<StockAssetDividend>
	 */
	public function findGreaterThan(ImmutableDateTime $greaterThanDate, int $limit = 10): array
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->gte('stockAssetDividend.exDate', ':greaterThanDate'),
		);
		$qb->setParameter('greaterThanDate', $greaterThanDate);
		$qb->setMaxResults($limit);
		$qb->orderBy('stockAssetDividend.exDate', 'ASC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividend>
	 */
	public function findLastDividends(int $limit = 8, StockAssetDividendTypeEnum|null $typeEnum = null): array
	{
		$qb = $this->createQueryBuilder();
		$qb->setMaxResults($limit);
		$qb->orderBy('stockAssetDividend.exDate', 'DESC');
		if ($typeEnum !== null) {
			$qb->andWhere($qb->expr()->eq('stockAssetDividend.dividendType', ':typeEnum'));
			$qb->setParameter('typeEnum', $typeEnum->value);
		}

		return $qb->getQuery()->getResult();
	}

	public function getLastDividend(StockAssetDividendTypeEnum|null $typeEnum = null): StockAssetDividend
	{
		$qb = $this->createQueryBuilder();
		$qb->orderBy('stockAssetDividend.exDate', 'DESC');
		if ($typeEnum !== null) {
			$qb->andWhere($qb->expr()->eq('stockAssetDividend.dividendType', ':typeEnum'));
			$qb->setParameter('typeEnum', $typeEnum->value);
		}

		$result = $qb->getQuery()->getSingleResult();
		assert($result instanceof StockAssetDividend);
		return $result;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
		$qb->innerJoin('stockAssetDividend.stockAsset', 'stockAsset');
		return $qb;
	}

}
