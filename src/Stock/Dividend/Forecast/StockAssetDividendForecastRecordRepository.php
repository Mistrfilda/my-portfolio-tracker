<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividendForecastRecord>
 */
class StockAssetDividendForecastRecordRepository extends BaseRepository
{

	public function getById(UuidInterface $id): StockAssetDividendForecastRecord
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('stockAssetDividendForecastRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetDividendForecastRecord);
			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<StockAssetDividendForecastRecord>
	 */
	public function findByStockAssetDividendForecast(
		UuidInterface $stockAssetDividendForecastId,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->eq(
				'stockAssetDividendForecastRecord.stockAssetDividendForecast',
				':stockAssetDividendForecastId',
			),
		);
		$qb->setParameter('stockAssetDividendForecastId', $stockAssetDividendForecastId);
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendForecastRecord');
		$qb->innerJoin('stockAssetDividendForecastRecord.stockAssetDividendForecast', 'forecast');
		$this->addAppAdminToQueryBuilder($qb, 'forecast', $appAdmin ?? $this->currentAppAdminGetter->getAppAdmin());
		return $qb;
	}

}
