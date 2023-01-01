<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Dashboard\DashboardValueBuilderFacade;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class PortfolioStatisticFacade
{

	public function __construct(
		private readonly DashboardValueBuilderFacade $dashboardValueBuilder,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
	)
	{
	}

	public function saveCurrentDashboardValues(): void
	{
		$dashboardValues = $this->dashboardValueBuilder->buildValues();
		$this->logger->info('Saving current dashboard values');

		$this->entityManager->beginTransaction();

		try {
			$portfolioStatisticRecord = new PortfolioStatisticRecord(
				$this->datetimeFactory->createNow(),
			);

			$this->entityManager->persist($portfolioStatisticRecord);

			foreach ($dashboardValues as $dashboardValueGroup) {
				foreach ($dashboardValueGroup->getPositions() as $dashboardValue) {
					$this->entityManager->persist(
						new PortfolioStatistic(
							$portfolioStatisticRecord,
							$this->datetimeFactory->createNow(),
							$dashboardValueGroup->getName(),
							$dashboardValue->getLabel(),
							$dashboardValue->getValue(),
							$dashboardValue->getColor(),
							$dashboardValue->getSvgIconEnum(),
							$dashboardValue->getDescription(),
						),
					);
				}
			}

			$this->entityManager->flush();
			$this->entityManager->commit();
		} catch (Throwable $e) {
			$this->entityManager->rollback();

			throw $e;
		}

		$this->logger->info('Saving of current dashboard values finished successfully');
	}

}
