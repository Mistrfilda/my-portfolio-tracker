<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Admin\AppAdmin;
use App\Doctrine\BaseRepository;
use Doctrine\ORM\QueryBuilder;
use UnexpectedValueException;

/** @extends BaseRepository<StockAiAnalysisSettings> */
class StockAiAnalysisSettingsRepository extends BaseRepository
{

	public function findSettings(AppAdmin|null $appAdmin = null): StockAiAnalysisSettings|null
	{
		$result = $this->createQueryBuilder($appAdmin)->getQuery()->getOneOrNullResult();
		if ($result !== null && !$result instanceof StockAiAnalysisSettings) {
			throw new UnexpectedValueException('Expected AI analysis settings.');
		}

		return $result;
	}

	public function createQueryBuilder(AppAdmin|null $appAdmin = null): QueryBuilder
	{
		$qb = $this->doctrineRepository->createQueryBuilder('stockAiAnalysisSettings');
		$this->addAppAdminToQueryBuilder(
			$qb,
			'stockAiAnalysisSettings',
			$appAdmin ?? $this->currentAppAdminGetter->getAppAdmin(),
		);
		return $qb;
	}

}
