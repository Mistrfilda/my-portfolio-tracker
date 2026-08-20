<?php

declare(strict_types = 1);

namespace App\Stock\Asset\Watchlist;

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
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetWatchlist');
		$qb->where($qb->expr()->eq('stockAssetWatchlist.id', ':id'));
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
		$result = $this->doctrineRepository->findOneBy(['ticker' => $ticker]);

		return $result instanceof StockAssetWatchlist ? $result : null;
	}

	/**
	 * @return array<StockAssetWatchlist>
	 */
	public function findAll(): array
	{
		return $this->doctrineRepository->findBy([], ['ticker' => OrderBy::ASC->value]);
	}

	public function createQueryBuilder(): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetWatchlist');
		$qb->orderBy('stockAssetWatchlist.ticker', OrderBy::ASC->value);

		return $qb;
	}

}
