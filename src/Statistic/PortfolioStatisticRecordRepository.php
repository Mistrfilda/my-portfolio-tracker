<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use App\Doctrine\LockModeEnum;
use App\Doctrine\NoEntityFoundException;
use App\Utils\TypeValidator;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<PortfolioStatisticRecord>
 */
class PortfolioStatisticRecordRepository extends BaseRepository
{

	public function findFirstBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->gte('portfolioStatisticRecord.createdAt', ':start'),
			$qb->expr()->lte('portfolioStatisticRecord.createdAt', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'ASC');
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortfolioStatisticRecord);
		return $result;
	}

	public function findLastBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->gte('portfolioStatisticRecord.createdAt', ':start'),
			$qb->expr()->lte('portfolioStatisticRecord.createdAt', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'DESC');
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortfolioStatisticRecord);
		return $result;
	}

	public function findFirst(AppAdmin|null $appAdmin = null): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'ASC');
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortfolioStatisticRecord);
		return $result;
	}

	public function findLast(AppAdmin|null $appAdmin = null): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'DESC');
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortfolioStatisticRecord);
		return $result;
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere(
			$qb->expr()->gte('portfolioStatisticRecord.createdAt', ':start'),
			$qb->expr()->lte('portfolioStatisticRecord.createdAt', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'portfolioStatisticRecord',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb;
	}

	/**
	 * Returns lightweight daily values for TWR, Modified Dietz, and XIRR calculations.
	 * Does not load full entities — works only with scalar values.
	 *
	 * @return array<array{date: ImmutableDateTime, amount: float, portfolioValue: float}>
	 */
	public function findDailyPerformanceValuesBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$rows = $this->createQueryBuilder($appAdmin)
			->select(
				'portfolioStatisticRecord.createdAt AS date',
				'investedStatistic.value AS amount',
				'portfolioValueStatistic.value AS portfolioValue',
			)
			->innerJoin(
				'portfolioStatisticRecord.portfolioStatistics',
				'investedStatistic',
				'WITH',
				'investedStatistic.type = :investedType',
			)
			->innerJoin(
				'portfolioStatisticRecord.portfolioStatistics',
				'portfolioValueStatistic',
				'WITH',
				'portfolioValueStatistic.type = :portfolioValueType',
			)
			->andWhere('portfolioStatisticRecord.createdAt >= :start')
			->andWhere('portfolioStatisticRecord.createdAt <= :end')
			->orderBy('portfolioStatisticRecord.createdAt', 'ASC')
			->setParameter('investedType', PortolioStatisticType::TOTAL_INVESTED_IN_CZK)
			->setParameter('portfolioValueType', PortolioStatisticType::TOTAL_VALUE_IN_CZK)
			->setParameter('start', $start)
			->setParameter('end', $end)
			->getQuery()
			->getScalarResult();

		$result = [];
		foreach ($rows as $row) {
			assert(is_array($row));
			$amount = str_replace(['CZK', ' '], '', TypeValidator::validateString($row['amount']));
			$dateRaw = $row['date'];
			$date = $dateRaw instanceof ImmutableDateTime
				? $dateRaw
				: new ImmutableDateTime(TypeValidator::validateString($dateRaw));
			$result[] = [
				'date' => $date,
				'amount' => (float) $amount,
				'portfolioValue' => (float) str_replace(
					['CZK', ' '],
					'',
					TypeValidator::validateString($row['portfolioValue']),
				),
			];
		}

		return $result;
	}

	/**
	 * @return array<array{date: ImmutableDateTime, portfolioValue: string, investedValue: string}>
	 */
	public function findDailyChartValuesBetweenDates(
		ImmutableDateTime $start,
		ImmutableDateTime $end,
		AppAdmin|null $appAdmin = null,
	): array
	{
		$rows = $this->createQueryBuilder($appAdmin)
			->select(
				'portfolioStatisticRecord.createdAt AS date',
				'portfolioValueStatistic.value AS portfolioValue',
				'investedValueStatistic.value AS investedValue',
			)
			->innerJoin(
				'portfolioStatisticRecord.portfolioStatistics',
				'portfolioValueStatistic',
				'WITH',
				'portfolioValueStatistic.type = :portfolioValueType',
			)
			->innerJoin(
				'portfolioStatisticRecord.portfolioStatistics',
				'investedValueStatistic',
				'WITH',
				'investedValueStatistic.type = :investedValueType',
			)
			->andWhere('portfolioStatisticRecord.createdAt >= :start')
			->andWhere('portfolioStatisticRecord.createdAt <= :end')
			->orderBy('portfolioStatisticRecord.createdAt', 'ASC')
			->setParameter('portfolioValueType', PortolioStatisticType::TOTAL_VALUE_IN_CZK)
			->setParameter('investedValueType', PortolioStatisticType::TOTAL_INVESTED_IN_CZK)
			->setParameter('start', $start)
			->setParameter('end', $end)
			->getQuery()
			->getScalarResult();

		$result = [];
		foreach ($rows as $row) {
			assert(is_array($row));
			$dateRaw = $row['date'];
			$result[] = [
				'date' => $dateRaw instanceof ImmutableDateTime
					? $dateRaw
					: new ImmutableDateTime(TypeValidator::validateString($dateRaw)),
				'portfolioValue' => TypeValidator::validateString($row['portfolioValue']),
				'investedValue' => TypeValidator::validateString($row['investedValue']),
			];
		}

		return $result;
	}

	public function getById(
		int $id,
		LockModeEnum|null $lockMode = null,
		AppAdmin|null $appAdmin = null,
	): PortfolioStatisticRecord
	{
		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->eq('portfolioStatisticRecord.id', ':id'));
		$qb->setParameter('id', $id);
		try {
			$query = $qb->getQuery();
			if ($lockMode !== null) {
				$query->setLockMode($lockMode->value);
			}

			$result = $query->getSingleResult();
			assert($result instanceof PortfolioStatisticRecord);

			return $result;
		} catch (NoResultException) {
			throw new NoEntityFoundException();
		}
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findAll(AppAdmin|null $appAdmin = null): array
	{
		return $this->createQueryBuilder($appAdmin)->getQuery()->getResult();
	}

	/**
	 * @param array<int> $ids
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findByIds(array $ids, AppAdmin|null $appAdmin = null): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->in('portfolioStatisticRecord.id', ':ids'));
		$qb->setParameter('ids', $ids);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findMinMaxDateByMonth(int $year, AppAdmin|null $appAdmin = null): array
	{
		$minDatesQb = $this->createQueryBuilder($appAdmin)
			->select(
				'MIN(portfolioStatisticRecord.createdAt) as minDate',
				'YEAR(portfolioStatisticRecord.createdAt) as eventYear',
				'MONTH(portfolioStatisticRecord.createdAt) as eventMonth',
			)
			->andWhere('YEAR(portfolioStatisticRecord.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$maxDatesQb = $this->createQueryBuilder($appAdmin)
			->select(
				'MAX(portfolioStatisticRecord.createdAt) as maxDate',
				'YEAR(portfolioStatisticRecord.createdAt) as eventYear',
				'MONTH(portfolioStatisticRecord.createdAt) as eventMonth',
			)
			->andWhere('YEAR(portfolioStatisticRecord.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$minDates = array_map(static fn ($result) => $result['minDate'], $minDatesQb->getQuery()->getResult());

		$maxDates = array_map(static fn ($result) => $result['maxDate'], $maxDatesQb->getQuery()->getResult());

		$qb = $this->createQueryBuilder($appAdmin);
		$qb->andWhere($qb->expr()->in('portfolioStatisticRecord.createdAt', ':dates'))
			->setParameter('dates', array_merge($minDates, $maxDates))
			->orderBy('portfolioStatisticRecord.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

}
