<?php

declare(strict_types = 1);

namespace App\Doctrine;

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

	/**
	 * @phpstan-param class-string<TEntityClass> $entityClass
	 */
	public function __construct(string $entityClass, EntityManagerInterface $entityManager)
	{
		/** @phpstan-var EntityRepository<TEntityClass> $doctrineRepository */
		$doctrineRepository = $entityManager->getRepository($entityClass);
		$this->doctrineRepository = $doctrineRepository;
		$this->entityManager = $entityManager;
	}

	abstract public function createQueryBuilder(): QueryBuilder;

}
