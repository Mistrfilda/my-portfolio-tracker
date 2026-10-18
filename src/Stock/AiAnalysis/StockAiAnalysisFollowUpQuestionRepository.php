<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAiAnalysisFollowUpQuestion>
 */
class StockAiAnalysisFollowUpQuestionRepository extends BaseRepository
{

	public function getById(UuidInterface $id, int|null $lockMode = null): StockAiAnalysisFollowUpQuestion
	{
		return $this->getFromQueryBuilder($this->createQueryBuilder(), $id, $lockMode);
	}

	// Workers derive the recipient and context from the question's parent run.
	public function getByIdForProcessing(UuidInterface $id): StockAiAnalysisFollowUpQuestion
	{
		return $this->getFromQueryBuilder(
			$this->doctrineRepository->createQueryBuilder('stockAiAnalysisFollowUpQuestion'),
			$id,
		);
	}

	private function getFromQueryBuilder(
		QueryBuilder $qb,
		UuidInterface $id,
		int|null $lockMode = null,
	): StockAiAnalysisFollowUpQuestion
	{
		$qb->andWhere($qb->expr()->eq('stockAiAnalysisFollowUpQuestion.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAiAnalysisFollowUpQuestion);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockAiAnalysisFollowUpQuestion>
	 */
	public function findByRun(StockAiAnalysisRun $stockAiAnalysisRun): array
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere('stockAiAnalysisFollowUpQuestion.stockAiAnalysisRun = :run');
		$qb->setParameter('run', $stockAiAnalysisRun);
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisFollowUpQuestion');
		$qb->innerJoin('stockAiAnalysisFollowUpQuestion.stockAiAnalysisRun', 'run');
		$this->addCurrentUserToQueryBuilder($qb, 'run');
		$qb->orderBy('stockAiAnalysisFollowUpQuestion.createdAt', 'DESC');
		return $qb;
	}

}
