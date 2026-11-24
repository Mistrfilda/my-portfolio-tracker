<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

use App\Admin\CurrentAppAdminGetter;
use App\Tax\TaxYearReportFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;

/**
 * @phpstan-import-type HarvestTimeEntry from HarvestTimeDownloader
 */
class WorkMonthlyIncomeFacade
{

	public function __construct(
		private int $currentHourlyRate,
		private WorkMonthlyIncomeRepository $workMonthlyIncomeRepository,
		private HarvestTimeDownloader $harvestTimeDownloader,
		private DatetimeFactory $datetimeFactory,
		private EntityManagerInterface $entityManager,
		private WorkMonthlyIncomeOwnerProvider $ownerProvider,
		private CurrentAppAdminGetter $currentAppAdminGetter,
		private TaxYearReportFacade|null $taxYearReportFacade = null,
	)
	{

	}

	public function download(): void
	{
		$appAdmin = $this->ownerProvider->getOwner();
		$serializedTimeEntries = [];

		/** @var array<HarvestTimeEntry> $timeEntries */
		$timeEntries = $this->harvestTimeDownloader->getData();
		foreach ($timeEntries as $timeEntry) {
			$key = DatetimeFactory::createFromFormat($timeEntry['spent_date'], 'Y-m-d')->format('Y-m');
			if (array_key_exists($key, $serializedTimeEntries)) {
				$serializedTimeEntries[$key] += $timeEntry['hours'];
				continue;
			}

			$serializedTimeEntries[$key] = $timeEntry['hours'];
		}

		$affectedTaxYears = [];
		foreach ($serializedTimeEntries as $key => $timeEntry) {
			$keyParts = explode('-', $key);
			$entryYear = (int) $keyParts[0];
			$entryMonth = (int) $keyParts[1];
			$affectedTaxYears[$entryYear] = true;
			if ($entryMonth === 12) {
				$affectedTaxYears[$entryYear + 1] = true;
			}

			$existingRow = $this->workMonthlyIncomeRepository->getByYearAndMonthForAppAdmin(
				$entryYear,
				$entryMonth,
				$appAdmin,
			);

			if ($existingRow !== null) {
				$existingRow->update(
					$timeEntry,
					$this->datetimeFactory->createNow(),
				);

				$this->entityManager->flush();
				continue;
			}

			$this->entityManager->persist(new WorkMonthlyIncome(
				$appAdmin,
				$entryYear,
				$entryMonth,
				$timeEntry,
				$this->currentHourlyRate,
				$this->datetimeFactory->createNow(),
			));
			$this->entityManager->flush();
		}

		if ($this->taxYearReportFacade !== null) {
			foreach (array_keys($affectedTaxYears) as $taxYear) {
				$this->taxYearReportFacade->enqueueOpenYearRefresh($taxYear, $appAdmin);
			}
		}
	}

	public function createBlank(int $year, int $month): WorkMonthlyIncome
	{
		$workMonthlyIncome = new WorkMonthlyIncome(
			$this->currentAppAdminGetter->getAppAdmin(),
			$year,
			$month,
			0,
			$this->currentHourlyRate,
			$this->datetimeFactory->createNow(),
		);

		$this->entityManager->persist($workMonthlyIncome);
		$this->entityManager->flush();
		$this->entityManager->refresh($workMonthlyIncome);
		return $workMonthlyIncome;
	}

}
