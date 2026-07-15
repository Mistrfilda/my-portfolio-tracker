<?php

declare(strict_types = 1);

namespace App\Statistic;

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
	): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder();
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
	): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder();
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

	public function findFirst(): PortfolioStatisticRecord|null
	{
		$qb = $this->createQueryBuilder();
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'ASC');
		$qb->setMaxResults(1);

		$result = $qb->getQuery()->getOneOrNullResult();
		assert($result === null || $result instanceof PortfolioStatisticRecord);
		return $result;
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findBetweenDates(ImmutableDateTime $start, ImmutableDateTime $end): array
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->gte('portfolioStatisticRecord.createdAt', ':start'),
			$qb->expr()->lte('portfolioStatisticRecord.createdAt', ':end'),
		);
		$qb->setParameter('start', $start);
		$qb->setParameter('end', $end);
		$qb->orderBy('portfolioStatisticRecord.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
	}

	/**
	 * Returns a lightweight list (createdAt, investedCzk) for Modified Dietz calculation.
	 * Does not load full entities — works only with scalar values.
	 *
	 * @return array<array{date: ImmutableDateTime, amount: float}>
	 */
	public function findDailyInvestedCzkBetweenDates(ImmutableDateTime $start, ImmutableDateTime $end): array
	{
		$rows = $this->doctrineRepository->createQueryBuilder('r')
			->select('r.createdAt AS date, ps.value AS amount')
			->innerJoin('r.portfolioStatistics', 'ps')
			->where('ps.type = :type')
			->andWhere('r.createdAt >= :start')
			->andWhere('r.createdAt <= :end')
			->orderBy('r.createdAt', 'ASC')
			->setParameter('type', PortolioStatisticType::TOTAL_INVESTED_IN_CZK)
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
			];
		}

		return $result;
	}

	/**
	 * @return array<array{date: ImmutableDateTime, portfolioValue: string, investedValue: string}>
	 */
	public function findDailyChartValuesBetweenDates(ImmutableDateTime $start, ImmutableDateTime $end): array
	{
		$rows = $this->doctrineRepository->createQueryBuilder('r')
			->select(
				'r.createdAt AS date',
				'portfolioValueStatistic.value AS portfolioValue',
				'investedValueStatistic.value AS investedValue',
			)
			->innerJoin(
				'r.portfolioStatistics',
				'portfolioValueStatistic',
				'WITH',
				'portfolioValueStatistic.type = :portfolioValueType',
			)
			->innerJoin(
				'r.portfolioStatistics',
				'investedValueStatistic',
				'WITH',
				'investedValueStatistic.type = :investedValueType',
			)
			->where('r.createdAt >= :start')
			->andWhere('r.createdAt <= :end')
			->orderBy('r.createdAt', 'ASC')
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

	public function getById(int $id, LockModeEnum|null $lockMode = null): PortfolioStatisticRecord
	{
		$qb = $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
		$qb->where($qb->expr()->eq('portfolioStatisticRecord.id', ':id'));
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
	public function findAll(): array
	{
		return $this->doctrineRepository->findAll();
	}

	/**
	 * @param array<int> $ids
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findByIds(array $ids): array
	{
		if (count($ids) === 0) {
			return [];
		}

		$qb = $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
		$qb->andWhere($qb->expr()->in('portfolioStatisticRecord.id', $ids));

		return $qb->getQuery()->getResult();
	}

	/**
	 * @return array<PortfolioStatisticRecord>
	 */
	public function findMinMaxDateByMonth(int $year): array
	{
		$minDatesQb = $this->doctrineRepository->createQueryBuilder('p')
			->select('MIN(p.createdAt) as minDate, YEAR(p.createdAt) as eventYear, MONTH(p.createdAt) as eventMonth')
			->where('YEAR(p.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$maxDatesQb = $this->doctrineRepository->createQueryBuilder('p')
			->select('MAX(p.createdAt) as maxDate, YEAR(p.createdAt) as eventYear, MONTH(p.createdAt) as eventMonth')
			->where('YEAR(p.createdAt) = :year')
			->groupBy('eventYear, eventMonth')
			->setParameter('year', $year);

		$minDates = array_map(static fn ($result) => $result['minDate'], $minDatesQb->getQuery()->getResult());

		$maxDates = array_map(static fn ($result) => $result['maxDate'], $maxDatesQb->getQuery()->getResult());

		$qb = $this->doctrineRepository->createQueryBuilder('p');
		$qb->where($qb->expr()->in('p.createdAt', ':dates'))
			->setParameter('dates', array_merge($minDates, $maxDates))
			->orderBy('p.createdAt', 'ASC');

		return $qb->getQuery()->getResult();
	}

}
