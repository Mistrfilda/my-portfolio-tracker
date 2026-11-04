<?php

declare(strict_types = 1);

namespace App\Stock\AiAnalysis;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

class StockAiAnalysisSettingsFacade
{

	public const string DEFAULT_INVESTOR_INSTRUCTIONS = 'Jsem dlouhodobý investor s preferencí kvalitních dividendových společností. '
		. 'Důležitá je udržitelnost dividend a dlouhodobý celkový výnos. Zajímavá společnost na plném nebo jednoduchém '
		. 'watchlistu nemusí vyplácet dividendu, pokud její kvalita, ocenění a investiční teze odůvodňují nákup. '
		. 'Absence dividendy sama o sobě není důvodem k jejímu odmítnutí.';

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
		$instructions = $this->repository->findSettings($appAdmin)?->getInvestorInstructions() ?? '';

		return trim($instructions) !== '' ? $instructions : self::DEFAULT_INVESTOR_INSTRUCTIONS;
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
