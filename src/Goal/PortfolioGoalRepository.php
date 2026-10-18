<?php

declare(strict_types = 1);

namespace App\Goal;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<PortfolioGoal>
 */
class PortfolioGoalRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): PortfolioGoal
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('portfolioGoal.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortfolioGoal);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<PortfolioGoal>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilder()->getQuery()->getResult();
	}

	/**
	 * @return array<PortfolioGoal>
	 */
	public function findActive(ImmutableDateTime $now, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createActiveQueryBuilder($now);
		$this->addAppAdminToQueryBuilder(
			$qb,
			'portfolioGoal',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb->getQuery()->getResult();
	}

	/** @return array<PortfolioGoal> */
	public function findActiveForProcessing(ImmutableDateTime $now, UuidInterface|null $appAdminId = null): array
	{
		// Scheduled jobs derive the owner from each persisted goal.
		$qb = $this->createActiveQueryBuilder($now);
		if ($appAdminId !== null) {
			$qb->andWhere('portfolioGoal.appAdmin = :owner');
			$qb->setParameter('owner', $appAdminId->toString());
		}

		return $qb->getQuery()->getResult();
	}

	private function createActiveQueryBuilder(ImmutableDateTime $now): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioGoal');
		$qb->andWhere(
			$qb->expr()->lt('portfolioGoal.startDate', ':now'),
			$qb->expr()->eq('portfolioGoal.active', ':active'),
		);

		$qb->setParameter('now', $now);
		$qb->setParameter('active', true);
		$qb->orderBy('portfolioGoal.startDate', 'ASC');
		return $qb;
	}

	public function findFirstActiveByType(PortfolioGoalTypeEnum $type): PortfolioGoal|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->eq('portfolioGoal.type', ':type'));
		$qb->andWhere($qb->expr()->eq('portfolioGoal.active', ':active'));
		$qb->setParameter('type', $type);
		$qb->setParameter('active', true);
		$qb->orderBy('portfolioGoal.startDate', 'ASC');
		$qb->setMaxResults(1);
		$result = $qb->getQuery()->getOneOrNullResult();
		if ($result === null) {
			return null;
		}

		assert($result instanceof PortfolioGoal);
		return $result;
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<PortfolioGoal>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->in('portfolioGoal.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioGoal');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'portfolioGoal',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb;
	}

}
