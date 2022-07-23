<?php

declare(strict_types = 1);

namespace App\Admin;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;
use function assert;

/**
 * @extends BaseRepository<AppAdmin>
 */
class AppAdminRepository extends BaseRepository
{

	public function findById(UuidInterface $appAdminId): AppAdmin
	{
		$qb = $this->doctrineRepository->createQueryBuilder('appAdmin');

		$qb->andWhere($qb->expr()->eq('appAdmin.id', ':id'));
		$qb->setParameter('id', $appAdminId);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof AppAdmin);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByUsername(string $username): AppAdmin
	{
		$qb = $this->doctrineRepository->createQueryBuilder('appAdmin');

		$qb->andWhere($qb->expr()->eq('appAdmin.username', ':username'));
		$qb->setParameter('username', $username);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof AppAdmin);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function findByEmail(string $email): AppAdmin
	{
		$qb = $this->doctrineRepository->createQueryBuilder('appAdmin');

		$qb->andWhere($qb->expr()->eq('appAdmin.email', ':email'));
		$qb->setParameter('email', $email);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof AppAdmin);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	public function getCount(): int
	{
		$qb = $this->doctrineRepository->createQueryBuilder('appAdmin');
		$qb->select('count(appAdmin.id)');
		$result = $qb->getQuery()->getSingleScalarResult();
		assert(is_scalar($result));

		return (int) $result;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('appAdmin');
	}

}
