<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Currency\CurrencyEnum;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockPosition>
 */
class StockPositionRepository extends BaseRepository
{

	public function getById(UuidInterface $id, LockModeEnum|null $lockMode = null): StockPosition
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockPosition');
		$qb->where($qb->expr()->eq('stockPosition.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockPosition>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @return array<StockPosition>
	 */
	public function findAllOpened(): array
	{
		$positions = $this->findAll();
		foreach ($positions as $key => $position) {
			if ($position->isPositionClosed()) {
				unset($positions[$key]);
			}
		}

		return $positions;
	}

	/**
	 * @return array<StockPosition>
	 */
	public function findAllOpenedInCurrency(CurrencyEnum $currencyEnum): array
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('stockPosition.stockAsset', 'stockAsset');

		$qb->andWhere($qb->expr()->eq('stockAsset.currency', ':currency'));
		$qb->setParameter('currency', $currencyEnum);

		$qb->andWhere($qb->expr()->isNull('stockPosition.stockClosedPosition'));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('stockPosition');
		$qb->andWhere($qb->expr()->in('stockPosition.id', $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForDatagrid(): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$qb->innerJoin('stockPosition.stockAsset', 'stockAsset');

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockPosition');
	}

}
