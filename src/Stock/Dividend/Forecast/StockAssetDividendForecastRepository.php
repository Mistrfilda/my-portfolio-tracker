<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Forecast;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\NoEntityFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividendForecast>
 */
class StockAssetDividendForecastRepository extends BaseRepository
{

	public function getById(UuidInterface $id, int|null $lockMode = null): StockAssetDividendForecast
	{
		return $this->getFromQueryBuilder($this->createQueryBuilder(), $id, $lockMode);
	}

	// Background jobs derive the owner from this persisted forecast.
	public function getByIdForProcessing(UuidInterface $id): StockAssetDividendForecast
	{
		return $this->getFromQueryBuilder(
			$this->doctrineRepository->createQueryBuilder('stockAssetDividendForecast'),
			$id,
		);
	}

	private function getFromQueryBuilder(
		QueryBuilder $qb,
		UuidInterface $id,
		int|null $lockMode = null,
	): StockAssetDividendForecast
	{
		$qb->andWhere($qb->expr()->eq('stockAssetDividendForecast.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode);
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetDividendForecast);

			return $result;
		} catch (NoResultException $e) {
			throw new NoEntityFoundException(previous: $e);
		}
	}

	public function findByDefaultForYear(int $year, AppAdmin|null $appAdmin = null): StockAssetDividendForecast|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('stockAssetDividendForecast.defaultForYear', ':defaultForYear'));
		$qb->setParameter('defaultForYear', true);
		$qb->andWhere($qb->expr()->eq('stockAssetDividendForecast.forYear', ':year'));
		$qb->setParameter('year', $year);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetDividendForecast);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<StockAssetDividendForecast>
	 */
	public function findAll(): array
	{
		return $this->createQueryBuilder()->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendForecast>
	 */
	public function findAllActive(int $year, AppAdmin $appAdmin): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->gte('stockAssetDividendForecast.forYear', ':year'));
		$qb->setParameter('year', $year);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAssetDividendForecast>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilder();
		$qb->andWhere($qb->expr()->in('stockAssetDividendForecast.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		$result = $qb->getQuery()->getResult();
		assert(is_array($result));

		return $result;
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendForecast');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'stockAssetDividendForecast',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb;
	}

}
