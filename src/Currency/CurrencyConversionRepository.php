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
		$exact = $this->findCurrencyPairConversionForDate($fromCurrency, $toCurrency, $date);
		if ($exact !== null) {
			return $exact;
		}

		$before = $this->findClosestBefore($fromCurrency, $toCurrency, $date);
		$after = $this->findClosestAfter($fromCurrency, $toCurrency, $date);

		if ($before === null && $after === null) {
			throw new NoResultException();
		}

		if ($before === null) {
			return $after;
		}

		if ($after === null) {
			return $before;
		}

		$diffBefore = abs($date->getTimestamp() - $before->getForDate()->getTimestamp());
		$diffAfter = abs($date->getTimestamp() - $after->getForDate()->getTimestamp());

		return $diffBefore <= $diffAfter ? $before : $after;
	}

	public function findClosestBefore(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime $date,
	): CurrencyConversion|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('currencyConversion.fromCurrency', ':fromCurrency'),
			$qb->expr()->eq('currencyConversion.toCurrency', ':toCurrency'),
			$qb->expr()->lte('currencyConversion.forDate', ':date'),
		);
		$qb->setParameter('fromCurrency', $fromCurrency);
		$qb->setParameter('toCurrency', $toCurrency);
		$qb->setParameter('date', $date);
		$qb->orderBy('currencyConversion.forDate', OrderBy::DESC->value);
		$qb->setMaxResults(1);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof CurrencyConversion);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function findClosestAfter(
		CurrencyEnum $fromCurrency,
		CurrencyEnum $toCurrency,
		ImmutableDateTime $date,
	): CurrencyConversion|null
	{
		$qb = $this->createQueryBuilder();
		$qb->andWhere(
			$qb->expr()->eq('currencyConversion.fromCurrency', ':fromCurrency'),
			$qb->expr()->eq('currencyConversion.toCurrency', ':toCurrency'),
			$qb->expr()->gte('currencyConversion.forDate', ':date'),
		);
		$qb->setParameter('fromCurrency', $fromCurrency);
		$qb->setParameter('toCurrency', $toCurrency);
		$qb->setParameter('date', $date);
		$qb->orderBy('currencyConversion.forDate', OrderBy::ASC->value);
		$qb->setMaxResults(1);

		try {
			$result = $qb->getQuery()->getSingleResult();
			assert($result instanceof CurrencyConversion);
			return $result;
		} catch (NoResultException) {
			return null;
		}
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('currencyConversion');
	}

}
