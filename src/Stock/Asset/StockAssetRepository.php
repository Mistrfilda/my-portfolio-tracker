<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAsset>
 */
class StockAssetRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): StockAsset
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->where($qb->expr()->eq('stockAsset.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAsset);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByTicker(string $ticker): StockAsset|null
	{
		return $this->doctrineRepository->findOneBy(['ticker' => $ticker]);
	}

	/**
	 * @return array<StockAsset>
	 */
	public function findAllByAssetPriceDownloader(
		StockAssetPriceDownloaderEnum $stockAssetPriceDownloader,
		int|null $limit = null,
		ImmutableDateTime|null $priceDownloadedBefore = null,
	): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->eq('stockAsset.assetPriceDownloader', ':assetPriceDownloader'));
		$qb->setParameter('assetPriceDownloader', $stockAssetPriceDownloader);

		$qb->andWhere(
			$qb->expr()->lte('stockAsset.priceDownloadedAt', ':priceDownloadedAt'),
		);

		if ($priceDownloadedBefore !== null) {
			$qb->setParameter('priceDownloadedAt', $priceDownloadedBefore);
		}

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAsset>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findBy([], ['name' => OrderBy::ASC->value]);
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAsset>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->in('stockAsset.id', $ids));

		$qb->orderBy('stockAsset.name', OrderBy::ASC->value);

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
	}

	/**
	 * @return array<string, string>
	 */
	public function findPairs(): array
	{
		$pairs = [];
		foreach ($this->findAll() as $stockAsset) {
			$pairs[$stockAsset->getId()->toString()] = $stockAsset->getName() . ' - ' . $stockAsset->getTicker();
		}

		return $pairs;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAsset');
	}

}
