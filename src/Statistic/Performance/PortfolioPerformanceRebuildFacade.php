<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Admin\AppAdmin;
use App\Statistic\PortfolioStatisticRecordRepository;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Throwable;

class PortfolioPerformanceRebuildFacade
{

	public function __construct(
		private readonly PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private readonly PortfolioPerformanceMonthRepository $portfolioPerformanceMonthRepository,
		private readonly PortfolioPerformanceReconstructor $portfolioPerformanceReconstructor,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly PortfolioStatisticTotalValueProvider $portfolioStatisticTotalValueProvider,
		private readonly PortfolioPerformanceEventProvider $portfolioPerformanceEventProvider,
	)
	{
	}

	public function rebuild(AppAdmin $appAdmin): int
	{
		$this->portfolioPerformanceEventProvider->invalidateCache($appAdmin);
		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst($appAdmin);
		$lastRecord = $this->portfolioStatisticRecordRepository->findLast($appAdmin);
		$months = [];

		if ($firstRecord !== null && $lastRecord !== null && $firstRecord !== $lastRecord) {
			$months = $this->portfolioPerformanceReconstructor->reconstruct(
				$appAdmin,
				$firstRecord->getCreatedAt(),
				$lastRecord->getCreatedAt(),
				0.0,
				$this->datetimeFactory->createNow(),
			);
		}

		$this->entityManager->beginTransaction();
		try {
			$this->portfolioPerformanceMonthRepository->deleteAll($appAdmin);
			foreach ($months as $month) {
				$this->entityManager->persist($month);
			}

			$this->entityManager->flush();
			$this->entityManager->commit();
		} catch (Throwable $exception) {
			$this->entityManager->rollback();

			throw $exception;
		}

		$this->portfolioStatisticTotalValueProvider->invalidateCache($appAdmin);
		$this->logger->info(
			'Portfolio performance cache rebuilt',
			['appAdminId' => $appAdmin->getId()->toString(), 'months' => count($months)],
		);
		return count($months);
	}

}
