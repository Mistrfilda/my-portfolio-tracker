<?php

declare(strict_types = 1);

namespace App\Statistic\Performance;

use App\Statistic\PortfolioStatisticRecordRepository;
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
	)
	{
	}

	public function rebuild(): int
	{
		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst();
		$lastRecord = $this->portfolioStatisticRecordRepository->findLast();
		$months = [];

		if ($firstRecord !== null && $lastRecord !== null && $firstRecord !== $lastRecord) {
			$months = $this->portfolioPerformanceReconstructor->reconstruct(
				$firstRecord->getCreatedAt(),
				$lastRecord->getCreatedAt(),
				0.0,
				$this->datetimeFactory->createNow(),
			);
		}

		$this->entityManager->beginTransaction();
		try {
			$this->portfolioPerformanceMonthRepository->deleteAll();
			foreach ($months as $month) {
				$this->entityManager->persist($month);
			}

			$this->entityManager->flush();
			$this->entityManager->commit();
		} catch (Throwable $exception) {
			$this->entityManager->rollback();

			throw $exception;
		}

		$this->logger->info('Portfolio performance cache rebuilt', ['months' => count($months)]);
		return count($months);
	}

}
