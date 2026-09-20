<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class StockAiAnalysisSettingsFacade
{

	public function __construct(
		private readonly StockAiAnalysisSettingsRepository $repository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function getInvestorInstructions(): string
	{
		return $this->repository->findSettings()?->getInvestorInstructions() ?? '';
	}

	public function saveInvestorInstructions(string $instructions): void
	{
		$settings = $this->repository->findSettings();
		$now = $this->datetimeFactory->createNow();
		if ($settings === null) {
			$settings = new StockAiAnalysisSettings($instructions, $now);
			$this->entityManager->persist($settings);
		} else {
			$settings->update($instructions, $now);
		}

		$this->entityManager->flush();
	}

}
