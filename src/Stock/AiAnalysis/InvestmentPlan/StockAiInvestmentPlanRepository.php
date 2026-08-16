<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAiInvestmentPlan>
 */
class StockAiInvestmentPlanRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockMode|null $lockMode = null): StockAiInvestmentPlan
	{
		$qb = $this->doctrineRepository->createQueryBuilder('investmentPlan');
		$qb->where($qb->expr()->eq('investmentPlan.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAiInvestmentPlan);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('investmentPlan');
		$qb->orderBy('investmentPlan.createdAt', 'DESC');

		return $qb;
	}

}
