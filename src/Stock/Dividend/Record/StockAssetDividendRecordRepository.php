<?php

declare(strict_types = 1);

namespace App\Stock\Dividend\Record;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Doctrine\OrderBy;
use App\Stock\Asset\StockAsset;
use App\Stock\Dividend\StockAssetDividend;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\UuidInterface;

/**
 * @extends BaseRepository<StockAssetDividendRecord>
 */
class StockAssetDividendRecordRepository extends BaseRepository
{

	public function getById(
		UuidInterface $id,
		LockModeEnum|null $lockMode = null,
		AppAdmin|null $appAdmin = null,
	): StockAssetDividendRecord
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('stockAssetDividendRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->toDoctrineLockMode());
			}

			$result = $query->getSingleResult();
			assert($result instanceof StockAssetDividendRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findAll(AppAdmin|null $appAdmin = null): array
	{
		return $this->createQueryBuilder($appAdmin)->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findAllForMonthChart(AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->orderBy('stockAssetDividend.exDate', OrderBy::ASC->value);
		return $qb->getQuery()->getResult();
	}

	/**
	 * @param array<UuidInterface> $ids
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilder($this->currentAppAdminGetter->getAppAdmin());
		$qb->andWhere($qb->expr()->in('stockAssetDividendRecord.id', ':ids'));
		$qb->setParameter('ids', array_map(static fn (UuidInterface $id): string => $id->toString(), $ids));

		return $qb->getQuery()->getResult();
	}

	public function findOneByStockDividend(
		StockAssetDividend $stockAssetDividend,
		AppAdmin|null $appAdmin = null,
	): StockAssetDividendRecord|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('stockAssetDividendRecord.stockAssetDividend', ':stockAssetDividend'));
		$qb->setParameter('stockAssetDividend', $stockAssetDividend);
		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof StockAssetDividendRecord);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByStockAssetSinceDate(
		StockAsset $stockAsset,
		ImmutableDateTime $date,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);

		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->gte('stockAssetDividend.exDate', ':exDate'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('exDate', $date);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByStockAssetForYear(StockAsset $stockAsset, int $year, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);

		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
			$qb->expr()->eq('YEAR(stockAssetDividend.exDate)', ':year'),
		);

		$qb->setParameter('stockAsset', $stockAsset);
		$qb->setParameter('year', $year);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByStockAsset(StockAsset $stockAsset, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);

		$qb->andWhere(
			$qb->expr()->eq('stockAssetDividend.stockAsset', ':stockAsset'),
		);

		$qb->setParameter('stockAsset', $stockAsset);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findByYearAndMonth(int|null $year, int|null $month, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);

		if ($year !== null) {
			$qb->andWhere(
				$qb->expr()->eq('YEAR(stockAssetDividend.exDate)', ':year'),
			);

			$qb->setParameter('year', $year);
		}

		if ($month !== null) {
			$qb->andWhere(
				$qb->expr()->eq('MONTH(stockAssetDividend.exDate)', ':month'),
			);

			$qb->setParameter('month', $month);
		}

		$qb->orderBy('stockAssetDividend.exDate', 'ASC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findGreaterThan(
		ImmutableDateTime $greaterThanDate,
		int $limit = 10,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->gte('stockAssetDividend.exDate', ':greaterThanDate'),
		);
		$qb->setParameter('greaterThanDate', $greaterThanDate);
		$qb->setMaxResults($limit);
		$qb->orderBy('stockAssetDividend.exDate', 'ASC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->gte('stockAssetDividend.exDate', ':start'),
			$qb->expr()->lte('stockAssetDividend.exDate', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('stockAssetDividend.exDate', 'ASC');
		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findCashReceivedBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->andX(
					$qb->expr()->isNotNull('stockAssetDividend.paymentDate'),
					$qb->expr()->gte('stockAssetDividend.paymentDate', ':start'),
					$qb->expr()->lte('stockAssetDividend.paymentDate', ':end'),
				),
				$qb->expr()->andX(
					$qb->expr()->isNull('stockAssetDividend.paymentDate'),
					$qb->expr()->gte('stockAssetDividend.exDate', ':start'),
					$qb->expr()->lte('stockAssetDividend.exDate', ':end'),
				),
			),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$records = $qb->getQuery()->getResult();
		usort(
			$records,
			static fn (StockAssetDividendRecord $left, StockAssetDividendRecord $right): int =>
				($left->getStockAssetDividend()->getPaymentDate()
					?? $left->getStockAssetDividend()->getExDate())
				<=>
				($right->getStockAssetDividend()->getPaymentDate()
					?? $right->getStockAssetDividend()->getExDate()),
		);

		return $records;
	}

	/**
	 * @return array<StockAssetDividendRecord>
	 */
	public function findLastDividendRecords(int $limit = 8, AppAdmin|null $appAdmin = null): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->setMaxResults($limit);
		$qb->orderBy('stockAssetDividend.exDate', 'DESC');
		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAssetDividendRecord');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'stockAssetDividendRecord',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		$qb->innerJoin('stockAssetDividendRecord.stockAssetDividend', 'stockAssetDividend');
		$qb->innerJoin('stockAssetDividend.stockAsset', 'stockAsset');
		return $qb;
	}

}
