<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;

/** @extends BaseRepository<StockAiAnalysisSettings> */
class StockAiAnalysisSettingsRepository extends BaseRepository
{

	public function findSettings(): StockAiAnalysisSettings|null
	{
		return $this->doctrineRepository->find(1);
	}

	public function createQueryBuilder(): QueryBuilder
	{
		return $this->doctrineRepository->createQueryBuilder('stockAiAnalysisSettings');
	}

}
