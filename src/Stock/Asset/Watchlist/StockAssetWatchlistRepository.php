<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetWatchlist>
 */
class StockAssetWatchlistRepository extends BaseRepository
{

	public function getById(UuidInterface $id): StockAssetWatchlist
	{
		return $this->getByIdForAppAdmin($id, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function getByIdForAppAdmin(UuidInterface $id, AppAdmin $appAdmin): StockAssetWatchlist
	{
		$qb = $this->createQueryBuilderForAppAdmin($appAdmin);
		$qb->andWhere($qb->expr()->eq('stockAssetWatchlist.id', ':id'));
		$qb->setParameter('id', $id);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetWatchlist);

			return $result;
		} catch (NoResultException $exception) {
			throw new NoEntityFoundException(previous: $exception);
		}
	}

	public function findByTicker(string $ticker): StockAssetWatchlist|null
	{
		return $this->findByTickerForAppAdmin($ticker, $this->currentAppAdminGetter->getAppAdmin());
	}

	public function findByTickerForAppAdmin(string $ticker, AppAdmin $appAdmin): StockAssetWatchlist|null
	{
		$result = $this->doctrineRepository->findOneBy(['ticker' => $ticker, 'appAdmin' => $appAdmin]);

		return $result instanceof StockAssetWatchlist ? $result : null;
	}

	/**
	 * @return array<StockAssetWatchlist>
	 */
	public function findAll(): array
	{
		return $this->findAllForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	/** @return array<StockAssetWatchlist> */
	public function findAllForAppAdmin(AppAdmin $appAdmin): array
	{
		return $this->createQueryBuilderForAppAdmin($appAdmin)->getQuery()->getResult();
	}

	public function createQueryBuilderForAppAdmin(AppAdmin $appAdmin): QueryBuilder
	{
		$qb = $this->createQueryBuilder();
		$this->addAppAdminToQueryBuilder($qb, 'stockAssetWatchlist', $appAdmin);

		return $qb;
	}

	public function createQueryBuilderForCurrentAppAdmin(): QueryBuilder
	{
		return $this->createQueryBuilderForAppAdmin($this->currentAppAdminGetter->getAppAdmin());
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetWatchlist');
		$qb->orderBy('stockAssetWatchlist.ticker', OrderBy::ASC->value);

		return $qb;
	}

}
