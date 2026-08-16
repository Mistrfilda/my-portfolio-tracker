<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAiAnalysisRun>
 */
class StockAiAnalysisRunRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockMode|null $lockMode = null): StockAiAnalysisRun
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisRun');
		$qb->where($qb->expr()->eq('stockAiAnalysisRun.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAiAnalysisRun);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockAiAnalysisRun>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findBy([], ['createdAt' => 'DESC']);
	}

	/**
	 * @return array<StockAiAnalysisRun>
	 */
	public function findCompletedPortfolioEvaluations(): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisRun');
		$qb->andWhere($qb->expr()->eq('stockAiAnalysisRun.analysisSchemaVersion', ':schemaVersion'));
		$qb->andWhere($qb->expr()->isNotNull('stockAiAnalysisRun.processedAt'));
		$qb->andWhere($qb->expr()->isNotNull('stockAiAnalysisRun.structuredData'));
		$qb->andWhere($qb->expr()->eq('stockAiAnalysisRun.includesPortfolio', ':includesPortfolio'));
		$qb->andWhere($qb->expr()->eq('stockAiAnalysisRun.portfolioPromptType', ':portfolioPromptType'));
		$qb->setParameter('schemaVersion', 2);
		$qb->setParameter('includesPortfolio', true);
		$qb->setParameter('portfolioPromptType', StockAiAnalysisPortfolioPromptTypeEnum::PORTFOLIO_EVALUATION);
		$qb->orderBy('stockAiAnalysisRun.processedAt', 'DESC');

		/** @var array<StockAiAnalysisRun> $results */
		$results = $qb->getQuery()->getResult();

		return $results;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisRun');
		$qb->orderBy('stockAiAnalysisRun.createdAt', 'DESC');
		return $qb;
	}

}
