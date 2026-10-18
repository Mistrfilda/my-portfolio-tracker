<?php

declare(strict_types = 1);

namespace App\Stock\Position;

use App\Admin\AppAdmin;
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
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin(), $lockMode);
	}

	public function getByIdForAppAdmin(
		UuidInterface $id,
		AppAdmin $appAdmin,
		LockModeEnum|null $lockMode = null,
	): StockPosition
	{
		return $this->getByIdFromQueryBuilder($this->createQueryBuilderForAppAdmin($appAdmin), $id, $lockMode);
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
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
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
	 * @param array<UuidInterface|string> $ids
	 * @return array<StockPosition>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->andWhere($qb->expr()->in('stockPosition.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface|string $id): string => (string) $id, $ids));

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilderForDatagrid(): QueryBuilder
	{
		$qb = $this->createQueryBuilderForCurrentAppAdmin();
		$qb->innerJoin('stockPosition.stockAsset', 'stockAsset');

		return $qb;
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'stockPosition', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addCurrentUserToQueryBuilder($qb, 'stockPosition');

		return $qb;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockPosition');
	}

	private function getByIdFromQueryBuilder(
		QueryBuilder $queryBuilder,
		UuidInterface $id,
		LockModeEnum|null $lockMode,
	): StockPosition
	{
		$queryBuilder->andWhere($queryBuilder->expr()->eq('stockPosition.id', ':id'));
		$queryBuilder->setParameter('id', $id);
		try {
			$query = $queryBuilder->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockPosition);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

}
