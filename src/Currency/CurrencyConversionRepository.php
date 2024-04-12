<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Doctrine\BaseRepository;
use App\Doctrine\OrderBy;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Mistrfilda\Datetime\Types\ImmutableDateTime;

/**
 * @extends BaseRepository<CurrencyConversion>
 */
class CurrencyConversionRepository extends BaseRepository
{

	public function getCurrentCurrencyPairConversion(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
	): CurrencyConversion
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('currencyConversion.fromCurrency', ':fromCurrency'),
			$qb->expr()->eq('currencyConversion.toCurrency', ':toCurrency'),
		);

		$qb->setParameter('fromCurrency', $fromCurrency);
		$qb->setParameter('toCurrency', $toCurrency);

		$qb->setMaxResults(1);
		$qb->orderBy('currencyConversion.forDate', OrderBy::DESC->value);

		$result = $qb->getQuery()->getSingleResult();
		assert($result instanceof CurrencyConversion);

		return $result;
	}

	public function findCurrencyPairConversionForDate(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime $date,
	): CurrencyConversion|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('currencyConversion.fromCurrency', ':fromCurrency'),
			$qb->expr()->eq('currencyConversion.toCurrency', ':toCurrency'),
			$qb->expr()->eq('currencyConversion.forDate', ':date'),
		);

		$qb->setParameter('fromCurrency', $fromCurrency);
		$qb->setParameter('toCurrency', $toCurrency);
		$qb->setParameter('date', $date);

		$qb->setMaxResults(1);
		$qb->orderBy('currencyConversion.forDate', OrderBy::DESC->value);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof CurrencyConversion);

			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function findCurrencyPairConversionForClosestDate(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime $date,
	): CurrencyConversion
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('currencyConversion.fromCurrency', ':fromCurrency'),
			$qb->expr()->eq('currencyConversion.toCurrency', ':toCurrency'),
		);

		$qb->setParameter('fromCurrency', $fromCurrency);
		$qb->setParameter('toCurrency', $toCurrency);

		$qb->setMaxResults(1);
		$qb->orderBy('abs(TIMESTAMPDIFF(second, currencyConversion.forDate, :date))');

		$qb->setParameter('date', $date);

		$result = $qb->getQuery()->getSingleResult();
		assert($result instanceof CurrencyConversion);
		return $result;
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('currencyConversion');
	}

}
