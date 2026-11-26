<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis\InvestmentPlan;

use App\Admin\AppAdmin;
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
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockMode|null $lockMode = null,
	): StockAiInvestmentPlan
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('investmentPlan.id', ':id'));
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

	/**
	 * @return array<StockAiInvestmentPlan>
	 */
	public function findLatestForAppAdmin(AppAdmin $appAdmin, int $limit = 5): array
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->setMaxResults($limit);

		/** @var array<StockAiInvestmentPlan> $results */
		$results = $qb->getQuery()->getResult();

		return $results;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('investmentPlan');
		$qb->innerJoin('investmentPlan.referenceAnalysisRun', 'run');
		$this->addAppAdminToQueryBuilder($qb, 'run', $appAdmin);
		$qb->orderBy('investmentPlan.createdAt', 'DESC');

		return $qb;
	}

}
