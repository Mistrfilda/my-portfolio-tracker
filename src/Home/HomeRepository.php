<?php

declare(strict_types = 1);

namespace App\Home;

use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<Home>
 */
class HomeRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): Home
	{
		$qb = $this->doctrineRepository->createQueryBuilder('home');
		$qb->where($qb->expr()->eq('home.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof Home);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<Home>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('home');
	}

}
