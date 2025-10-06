<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Industry;

use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetIndustry>
 */
class StockAssetIndustryRepository extends BaseRepository
{

	public function getById(UuidInterface $id, int|null $lockMode = null): StockAssetIndustry
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetIndustry');
		$qb->where($qb->expr()->eq('stockAssetIndustry.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetIndustry);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	/**
	 * @return array<StockAssetIndustry>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAssetIndustry>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetIndustry');
		$qb->andWhere($qb->expr()->in('stockAssetIndustry.id', $ids));

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
	}

	/**
	 * @return array<string, string>
	 */
	public function findPairs(): array
	{
		$pairs = [];
		foreach ($this->findAll() as $stockAssetIndustry) {
			$pairs[$stockAssetIndustry->getId()->toString()] = $stockAssetIndustry->getName();
		}

		return $pairs;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAssetIndustry');
	}

}
