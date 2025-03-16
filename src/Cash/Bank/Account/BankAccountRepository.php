<?php

declare(strict_types = 1);

namespace App\Cash\Bank\Account;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<BankAccount>
 */
class BankAccountRepository extends BaseRepository
{

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('bankAccount');
	}

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): BankAccount
	{
		$qb = $this->doctrineRepository->createQueryBuilder('bankAccount');
		$qb->where($qb->expr()->eq('bankAccount.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof BankAccount);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<BankAccount>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @return array<string, string>
	 */
	public function findPairs(): array
	{
		$pairs = [];
		foreach ($this->findAll() as $bankAccount) {
			$pairs[$bankAccount->getId()->toString()] = sprintf(
				'%s (%s)',
				$bankAccount->getName(),
				$bankAccount->getBank(),
			);
		}

		return $pairs;
	}

}
