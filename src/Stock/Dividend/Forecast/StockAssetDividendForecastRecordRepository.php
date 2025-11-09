<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividendForecastRecord>
 */
class StockAssetDividendForecastRecordRepository extends BaseRepository
{

	/**
	 * @return array<StockAssetDividendForecastRecord>
	 */
	public function findByStockAssetDividendForecast(UuidInterface $stockAssetDividendForecastId): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendForecastRecord');
		$qb->andWhere(
			$qb->expr()->eq(
				'stockAssetDividendForecastRecord.stockAssetDividendForecast',
				':stockAssetDividendForecastId',
			),
		);
		$qb->setParameter('stockAssetDividendForecastId', $stockAssetDividendForecastId);
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAssetDividendForecastRecord');
	}

}
