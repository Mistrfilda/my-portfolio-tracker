<?php

declare(strict_types = 1);

namespace App\Stock\Asset;

use App\Asset\Asset;
use App\Asset\AssetRepository;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use App\Stock\Asset\Download\StockAssetDataType;
use App\Stock\Dividend\StockAssetDividendSourceEnum;
use App\Stock\Price\StockAssetPriceDownloaderEnum;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAsset>
 */
class StockAssetRepository extends BaseRepository implements AssetRepository
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

	/**
	 * @return array<Asset>
	 */
	public function getAllActiveAssets(): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAsset>
	 */
	public function getAllActiveValuationAssets(): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadValuation', ':shouldDownloadValuation'));
		$qb->setParameter('shouldDownloadValuation', true);
		return $qb->getQuery()->getResult();
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

		if ($priceDownloadedBefore !== null) {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->isNull('stockAsset.priceDownloadedAt'),
					$qb->expr()->lte('stockAsset.priceDownloadedAt', ':priceDownloadedAt'),
				),
			);

			$qb->setParameter('priceDownloadedAt', $priceDownloadedBefore);
		}

		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}

		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);

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
	 * @return array<StockAsset>
	 */
	public function findAllActive(): array
	{
		return $this->doctrineRepository->findBy(['shouldDownloadPrice' => true], ['name' => OrderBy::ASC->value]);
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

		return $qb->getQuery()->getResult();
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

	/**
	 * @return array<int, StockAsset>
	 */
	public function findByStockAssetDividendSource(StockAssetDividendSourceEnum $stockAssetDividendSourceEnum): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->eq('stockAsset.stockAssetDividendSource', ':stockAssetDividendSource'));
		$qb->setParameter('stockAssetDividendSource', $stockAssetDividendSourceEnum->value);

		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<int, StockAsset>
	 */
	public function findDividendPayers(): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->andWhere($qb->expr()->isNotNull('stockAsset.stockAssetDividendSource'));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAsset');
	}

	public function getEnabledCount(): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->select('count(stockAsset.id)');
		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);
		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

	public function getDividendsEnabledCount(): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->select('count(stockAsset.id)');

		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);

		$qb->andWhere($qb->expr()->eq('stockAsset.stockAssetDividendSource', ':stockAssetDividendSource'));
		$qb->setParameter('stockAssetDividendSource', StockAssetDividendSourceEnum::WEB);

		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));
		return (int) $result;
	}

	public function getValuationEnabledCount(): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->select('count(stockAsset.id)');

		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadValuation', ':shouldDownloadValuation'));
		$qb->setParameter('shouldDownloadValuation', true);

		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));
		return (int) $result;
	}

	public function getCountUpdatedPricesAt(ImmutableDateTime $date, int $hour): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->select('count(stockAsset.id)');
		$qb->andWhere($qb->expr()->eq('HOUR(stockAsset.priceDownloadedAt)', ':hour'));
		$qb->setParameter('hour', $hour);
		$qb->andWhere($qb->expr()->eq('DATE(stockAsset.priceDownloadedAt)', ':date'));
		$qb->setParameter('date', $date);
		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

	public function getCountUpdatedPricesSince(ImmutableDateTime $date): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAsset');
		$qb->select('count(stockAsset.id)');

		$qb->andWhere($qb->expr()->gte('stockAsset.priceDownloadedAt', ':date'));
		$qb->setParameter('date', $date);

		$qb->andWhere($qb->expr()->eq('stockAsset.shouldDownloadPrice', ':shouldDownloadPrice'));
		$qb->setParameter('shouldDownloadPrice', true);

		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

	public function countForDataType(
		StockAssetDataType $type,
		ImmutableDateTime|null $updatedSince = null,
		StockAssetPriceDownloaderEnum|null $source = null,
	): int
	{
		$qb = $this->createDataTypeQuery($type, $source);
		if ($updatedSince !== null) {
			$qb->andWhere(sprintf('stockAsset.%s >= :since', $type->value));
			$qb->setParameter('since', $updatedSince);
		}

		return (int) $qb->getQuery()->getSingleScalarResult();
	}

	public function countStaleForDataType(
		StockAssetDataType $type,
		ImmutableDateTime $updatedSince,
		ImmutableDateTime $createdBefore,
		StockAssetPriceDownloaderEnum|null $source = null,
	): int
	{
		$qb = $this->createDataTypeQuery($type, $source);
		$qb->andWhere(sprintf('(stockAsset.%1$s IS NULL OR stockAsset.%1$s < :since)', $type->value));
		$qb->andWhere('stockAsset.createdAt <= :createdBefore');
		$qb->setParameter('since', $updatedSince);
		$qb->setParameter('createdBefore', $createdBefore);
		return (int) $qb->getQuery()->getSingleScalarResult();
	}

	private function createDataTypeQuery(
		StockAssetDataType $type,
		StockAssetPriceDownloaderEnum|null $source,
	): QueryBuilder
	{
		$qb = $this->createQueryBuilder()->select('COUNT(stockAsset.id)');
		$flag = in_array(
			$type,
			[StockAssetDataType::PRICE, StockAssetDataType::DIVIDENDS],
			true,
		)
			? 'shouldDownloadPrice'
			: 'shouldDownloadValuation';
		$qb->andWhere(sprintf('stockAsset.%s = :enabled', $flag));
		$qb->setParameter('enabled', true);
		if ($type === StockAssetDataType::DIVIDENDS) {
			$qb->andWhere('stockAsset.stockAssetDividendSource = :dividendSource');
			$qb->setParameter('dividendSource', StockAssetDividendSourceEnum::WEB->value);
		}

		if ($source !== null) {
			$qb->andWhere('stockAsset.assetPriceDownloader = :source');
			$qb->setParameter('source', $source->value);
		}

		return $qb;
	}

}
