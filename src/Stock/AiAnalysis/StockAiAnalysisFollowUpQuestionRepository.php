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
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisFollowUpQuestion');
		$qb->where($qb->expr()->eq('stockAiAnalysisFollowUpQuestion.id', ':id'));
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
		return $this->doctrineRepository->findBy(
			['stockAiAnalysisRun' => $stockAiAnalysisRun],
			['createdAt' => 'DESC'],
		);
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisFollowUpQuestion');
		$qb->orderBy('stockAiAnalysisFollowUpQuestion.createdAt', 'DESC');
		return $qb;
	}

}
