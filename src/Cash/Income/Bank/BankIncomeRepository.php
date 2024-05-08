<?php

declare(strict_types = 1);

namespace App\Cash\Income\Bank;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<BankIncome>
 */
class BankIncomeRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): BankIncome
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankIncome');
		$qb->where($qb->expr()->eq('bankIncome.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankIncome);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	public function findByIdentifier(string $identifier, LockModeEnum|null $lockMode = null): BankIncome|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankIncome');
		$qb->where($qb->expr()->eq('bankIncome.identifier', ':identifier'));
		$qb->setParameter('identifier', $identifier);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankIncome);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<BankIncome>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<BankIncome>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('bankIncome');
		$qb->andWhere($qb->expr()->in('bankIncome.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('bankIncome');
	}

}
