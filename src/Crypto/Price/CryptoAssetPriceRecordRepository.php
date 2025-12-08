<?php

declare(strict_types = 1);

namespace App\Crypto\Price;

use App\Crypto\Asset\CryptoAsset;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<CryptoAssetPriceRecord>
 */
class CryptoAssetPriceRecordRepository extends BaseRepository
{

	public function getById(int $id, LockModeEnum|null $lockMode = null): CryptoAssetPriceRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAssetPriceRecord');
		$qb->where($qb->expr()->eq('cryptoAssetPriceRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof CryptoAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByCryptoAssetAndDate(
		CryptoAsset $cryptoAsset,
		ImmutableDateTime $date,
	): CryptoAssetPriceRecord|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAssetPriceRecord');

		$qb->andWhere(
			$qb->expr()->eq('cryptoAssetPriceRecord.date', ':date'),
			$qb->expr()->eq('cryptoAssetPriceRecord.cryptoAsset', ':cryptoAsset'),
		);

		$qb->setParameter('date', $date);
		$qb->setParameter('cryptoAsset', $cryptoAsset);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof CryptoAssetPriceRecord);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<CryptoAssetPriceRecord>
	 */
	public function findByCryptoAssetSinceDate(CryptoAsset $cryptoAsset, ImmutableDateTime $date): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAssetPriceRecord');

		$qb->andWhere(
			$qb->expr()->gte('cryptoAssetPriceRecord.date', ':date'),
			$qb->expr()->eq('cryptoAssetPriceRecord.cryptoAsset', ':cryptoAsset'),
		);

		$qb->setParameter('date', $date);
		$qb->setParameter('cryptoAsset', $cryptoAsset);

		$qb->orderBy('cryptoAssetPriceRecord.date', OrderBy::ASC->value);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<CryptoAssetPriceRecord>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<CryptoAssetPriceRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAssetPriceRecord');
		$qb->andWhere($qb->expr()->in('cryptoAssetPriceRecord.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('cryptoAssetPriceRecord');
	}

}
