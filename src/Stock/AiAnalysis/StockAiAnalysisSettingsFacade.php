<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class StockAiAnalysisSettingsFacade
{

	public function __construct(
		private readonly StockAiAnalysisSettingsRepository $repository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function getInvestorInstructions(AppAdmin|null $appAdmin = null): string
	{
		return $this->repository->findSettings($appAdmin)?->getInvestorInstructions() ?? '';
	}

	public function saveInvestorInstructions(string $instructions): void
	{
		$settings = $this->repository->findSettings();
		$now = $this->datetimeFactory->createNow();
		if ($settings === null) {
			$settings = new StockAiAnalysisSettings($this->currentAppAdminGetter->getAppAdmin(), $instructions, $now);
			$this->entityManager->persist($settings);
		} else {
			$settings->update($instructions, $now);
		}

		$this->entityManager->flush();
	}

}
