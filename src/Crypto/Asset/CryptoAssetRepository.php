<?php

declare(strict_types = 1);

namespace App\Crypto\Asset;

use App\Asset\Asset;
use App\Asset\AssetRepository;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<CryptoAsset>
 */
class CryptoAssetRepository extends BaseRepository implements AssetRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): CryptoAsset
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAsset');
		$qb->where($qb->expr()->eq('cryptoAsset.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof CryptoAsset);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<Asset>
	 */
	public function getAllActiveAssets(): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAsset');
		return $qb->getQuery()->getResult();
	}

	public function findByTicker(string $ticker): CryptoAsset|null
	{
		return $this->doctrineRepository->findOneBy(['ticker' => $ticker]);
	}

	/**
	 * @return array<CryptoAsset>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findBy([], ['name' => OrderBy::ASC->value]);
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<CryptoAsset>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAsset');
		$qb->andWhere($qb->expr()->in('cryptoAsset.id', $ids));

		$qb->orderBy('cryptoAsset.name', OrderBy::ASC->value);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<string, string>
	 */
	public function findPairs(): array
	{
		$pairs = [];
		foreach ($this->findAll() as $cryptoAsset) {
			$pairs[$cryptoAsset->getId()->toString()] = $cryptoAsset->getName() . ' - ' . $cryptoAsset->getTicker();
		}

		return $pairs;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('cryptoAsset');
	}

	public function getEnabledCount(): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAsset');
		$qb->select('count(cryptoAsset.id)');
		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

	public function getCountUpdatedPricesAt(ImmutableDateTime $date, int $hour): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('cryptoAsset');
		$qb->select('count(cryptoAsset.id)');
		$qb->andWhere($qb->expr()->eq('HOUR(cryptoAsset.priceDownloadedAt)', ':hour'));
		$qb->setParameter('hour', $hour);
		$qb->andWhere($qb->expr()->eq('DATE(cryptoAsset.priceDownloadedAt)', ':date'));
		$qb->setParameter('date', $date);
		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

}
