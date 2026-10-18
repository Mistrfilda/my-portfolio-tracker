<?php

declare(strict_types = 1);

namespace App\Cash\Income\WorkMonthlyIncome;

use App\Admin\CurrentAppAdminGetter;
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

		foreach ($serializedTimeEntries as $key => $timeEntry) {
			$keyParts = explode('-', $key);
			$existingRow = $this->workMonthlyIncomeRepository->getByYearAndMonthForAppAdmin(
				(int) $keyParts[0],
				(int) $keyParts[1],
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
				(int) $keyParts[0],
				(int) $keyParts[1],
				$timeEntry,
				$this->currentHourlyRate,
				$this->datetimeFactory->createNow(),
			));
			$this->entityManager->flush();
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
