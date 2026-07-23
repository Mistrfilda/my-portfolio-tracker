<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Dashboard\DashboardValueBuilderFacade;
use App\Dashboard\DashboardValueGroupEnum;
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

	public function saveCurrentDashboardValues(bool $includePortfolioPerformance = true): PortfolioStatisticRecord
	{
		$dashboardValues = $this->dashboardValueBuilder->buildValues($includePortfolioPerformance);
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
							$dashboardValue->getType(),
							PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
							null,
						),
					);
				}

				foreach ($dashboardValueGroup->getTables() as $table) {
					$this->entityManager->persist(
						new PortfolioStatistic(
							$portfolioStatisticRecord,
							$this->datetimeFactory->createNow(),
							$dashboardValueGroup->getName(),
							$table->getLabel(),
							$table->getValue(),
							$table->getColor(),
							null,
							null,
							null,
							PortfolioStatisticControlTypeEnum::TABLE,
							['data' => $table->getData(), 'heading' => $table->getHeading()],
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
		return $portfolioStatisticRecord;
	}

	public function appendPortfolioPerformanceValues(PortfolioStatisticRecord $record): void
	{
		$now = $this->datetimeFactory->createNow();
		foreach ($this->dashboardValueBuilder->buildPortfolioPerformanceValues() as $dashboardValue) {
			$this->entityManager->persist(
				new PortfolioStatistic(
					$record,
					$now,
					DashboardValueGroupEnum::TOTAL_VALUES,
					$dashboardValue->getLabel(),
					$dashboardValue->getValue(),
					$dashboardValue->getColor(),
					$dashboardValue->getSvgIconEnum(),
					$dashboardValue->getDescription(),
					$dashboardValue->getType(),
					PortfolioStatisticControlTypeEnum::SIMPLE_VALUE,
					null,
				),
			);
		}

		$this->entityManager->flush();
	}

}
