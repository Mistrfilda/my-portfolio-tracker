<?php

declare(strict_types = 1);

namespace App\Doctrine;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @template TEntityClass as object
 */
abstract class BaseRepository
{

	/** @phpstan-var EntityRepository<TEntityClass> */
	protected EntityRepository $doctrineRepository;

	protected EntityManagerInterface $entityManager;

	protected CurrentAppAdminGetter $currentAppAdminGetter;

	/**
	 * @phpstan-param class-string<TEntityClass> $entityClass
	 */
	public function __construct(
		string $entityClass,
		EntityManagerInterface $entityManager,
		CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
		/** @phpstan-var EntityRepository<TEntityClass> $doctrineRepository */
		$doctrineRepository = $entityManager->getRepository($entityClass);
		$this->doctrineRepository = $doctrineRepository;
		$this->entityManager = $entityManager;
		$this->currentAppAdminGetter = $currentAppAdminGetter;
	}

	protected function addCurrentUserToQueryBuilder(QueryBuilder $queryBuilder, string $alias): void
	{
		$this->addAppAdminToQueryBuilder($queryBuilder, $alias, $this->currentAppAdminGetter->getAppAdmin());
	}

	protected function addAppAdminToQueryBuilder(QueryBuilder $queryBuilder, string $alias, AppAdmin $appAdmin): void
	{
		$parameterIndex = 0;
		do {
			$key = 'appAdmin' . $parameterIndex++;
		} while ($queryBuilder->getParameter($key) !== null);

		$queryBuilder->andWhere($queryBuilder->expr()->eq($alias . '.appAdmin', ':' . $key));
		$queryBuilder->setParameter($key, $appAdmin);
	}

	abstract public function createQueryBuilder(): QueryBuilder;

}
