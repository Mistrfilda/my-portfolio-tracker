<?php

declare(strict_types = 1);

namespace App\Goal;

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
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioGoal');
		$qb->where($qb->expr()->eq('portfolioGoal.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
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
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @return array<PortfolioGoal>
	 */
	public function findActive(ImmutableDateTime $now): array
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioGoal');
		$qb->andWhere(
			$qb->expr()->lt('portfolioGoal.startDate', ':now'),
			$qb->expr()->eq('portfolioGoal.active', ':active'),
		);

		$qb->setParameter('now', $now);
		$qb->setParameter('active', true);
		$qb->orderBy('portfolioGoal.startDate', 'ASC');
		return $qb->getQuery()->getResult();
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

		$qb = $this->doctrineRepository->createQueryBuilder('portfolioGoal');
		$qb->andWhere($qb->expr()->in('portfolioGoal.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioGoal');
	}

}
