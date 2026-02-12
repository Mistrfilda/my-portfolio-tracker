<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAiAnalysisRun>
 */
class StockAiAnalysisRunRepository extends BaseRepository
{

	public function getById(UuidInterface $id, int|null $lockMode = null): StockAiAnalysisRun
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

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisRun');
		$qb->orderBy('stockAiAnalysisRun.createdAt', 'DESC');
		return $qb;
	}

}
