<?php

declare(strict_types = 1);

namespace App\Currency;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<CurrencyConversion>
 */
class CurrencyConversionRepository extends BaseRepository
{

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('currencyConversion');
	}

}
