<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\BaseRepository;
use App\Stock\Asset\StockAsset;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<StockAiAnalysisStockResult>
 */
class StockAiAnalysisStockResultRepository extends BaseRepository
{

	/**
	 * @return array<StockAiAnalysisStockResult>
	 */
	public function findLatestForStockAsset(StockAsset $stockAsset, int $limit = 5): array
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('stockAiAnalysisStockResult.stockAsset', ':stockAsset'));
		$qb->setParameter('stockAsset', $stockAsset);
		$qb->orderBy('run.createdAt', 'DESC');
		$qb->setMaxResults($limit);
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisStockResult');
		$qb->innerJoin('stockAiAnalysisStockResult.stockAiAnalysisRun', 'run');
		$this->addCurrentUserToQueryBuilder($qb, 'run');
		return $qb;
	}

}
