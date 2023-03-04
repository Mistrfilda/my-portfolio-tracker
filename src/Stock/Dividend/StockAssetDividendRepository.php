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

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
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

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAssetDividend');
	}

}
