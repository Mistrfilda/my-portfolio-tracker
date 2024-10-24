<?php

declare(strict_types = 1);

namespace App\System;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<SystemValue>
 */
class SystemValueRepository extends BaseRepository
{

	public function findByEnum(SystemValueEnum $systemValueEnum): SystemValue|null
	{
		$qb = $this->doctrineRepository->createQueryBuilder('systemValue');
		$qb->where($qb->expr()->eq('systemValue.systemValueEnum', ':systemValueEnum'));
		$qb->setParameter('systemValueEnum', $systemValueEnum);

		try {
			$query = $qb->getQuery();
			$result = $query->getSingleResult();
			assert($result instanceof SystemValue);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<SystemValue>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<SystemValue>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('systemValue');
		$qb->andWhere($qb->expr()->in('systemValue.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('systemValue');
	}

}
