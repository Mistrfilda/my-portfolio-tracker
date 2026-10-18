<?php

declare(strict_types = 1);

namespace App\Statistic;

use App\Admin\AppAdmin;
use App\Admin\AppAdminRepository;
use App\Statistic\Performance\PortfolioPerformanceEventProvider;
use App\Statistic\Performance\PortfolioPerformanceRebuildFacade;
use App\Statistic\Total\PortfolioStatisticTotalValueProvider;
use Doctrine\DBAL\Exception as DatabaseException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Throwable;

class PortfolioStatisticBatchFacade
{

	public function __construct(
		private readonly AppAdminRepository $appAdminRepository,
		private readonly PortfolioStatisticFacade $portfolioStatisticFacade,
		private readonly PortfolioPerformanceRebuildFacade $portfolioPerformanceRebuildFacade,
		private readonly EntityManagerInterface $entityManager,
		private readonly PortfolioStatisticTotalValueProvider $portfolioStatisticTotalValueProvider,
		private readonly PortfolioPerformanceEventProvider $portfolioPerformanceEventProvider,
		private readonly LoggerInterface $logger,
	)
	{
	}

	public function saveForAll(): bool
	{
		return $this->processAll(saveSnapshot: true);
	}

	public function rebuildForAll(): bool
	{
		return $this->processAll(saveSnapshot: false);
	}

	private function processAll(bool $saveSnapshot): bool
	{
		$ownerIds = array_map(
			static fn (AppAdmin $appAdmin): UuidInterface => $appAdmin->getId(),
			$this->appAdminRepository->findAll(),
		);
		$successful = true;
		foreach ($ownerIds as $ownerId) {
			$appAdmin = null;
			try {
				// Load a managed owner after clearing the previous account's entities.
				$appAdmin = $this->appAdminRepository->findById($ownerId);
				$months = $this->entityManager->getConnection()->transactional(
					function () use ($appAdmin, $saveSnapshot): int {
						$record = $saveSnapshot
							? $this->portfolioStatisticFacade->saveCurrentDashboardValues(
								$appAdmin,
								false,
							)
							: null;
						$months = $this->portfolioPerformanceRebuildFacade->rebuild($appAdmin);
						if ($record !== null) {
							$this->portfolioStatisticFacade->appendPortfolioPerformanceValues($record);
						}

						return $months;
					},
				);
				$this->logger->info('Portfolio statistics processed for account', [
					'appAdminId' => $ownerId->toString(),
					'months' => $months,
					'snapshotSaved' => $saveSnapshot,
				]);
			} catch (Throwable $exception) {
				$successful = false;
				$this->logger->error('Portfolio statistics failed for account', [
					'appAdminId' => $ownerId->toString(),
					'exception' => $exception,
				]);
				if ($exception instanceof DatabaseException || !$this->entityManager->isOpen()) {
					$this->logger->error('Stopping portfolio statistics after a persistence failure');
					return false;
				}
			} finally {
				$this->entityManager->clear();
				if ($appAdmin !== null) {
					// Cache writes are outside the transaction, including a rolled-back calculation.
					$this->portfolioStatisticTotalValueProvider->invalidateCache($appAdmin);
					$this->portfolioPerformanceEventProvider->invalidateCache($appAdmin);
				}
			}
		}

		return $successful;
	}

}
