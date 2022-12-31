<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends BaseRepository<PortfolioStatisticRecord>
 */
class PortfolioStatisticRecordRepository extends BaseRepository
{

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('portfolioStatisticRecord');
	}

}
