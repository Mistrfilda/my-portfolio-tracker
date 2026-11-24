<?php

declare(strict_types = 1);

namespace App\Tax;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\JobRequest\JobRequestFacade;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

class TaxYearReportFacade
{

	public function __construct(
		private TaxYearReportRepository $reportRepository,
		private TaxYearProfileRepository $profileRepository,
		private TaxYearReportBuilder $reportBuilder,
		private JobRequestFacade $jobRequestFacade,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private LoggerInterface $logger,
		private CurrentAppAdminGetter $currentAppAdminGetter,
	)
	{
	}

	public function enqueue(int $year, AppAdmin|null $appAdmin = null): TaxYearReport
	{
		$this->validateYear($year);
		$owner = $appAdmin ?? $this->currentAppAdminGetter->getAppAdmin();
		$now = $this->datetimeFactory->createNow();

		$report = new TaxYearReport($owner, $year, $now, $now);
		$this->entityManager->persist($report);
		$this->entityManager->flush();

		try {
			$this->jobRequestFacade->addTaxYearReportProcessToQueue($report->getId()->toString());
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();

			throw $exception;
		}

		return $report;
	}

	public function enqueueOpenYearRefresh(int $year, AppAdmin|null $appAdmin = null): TaxYearReport|null
	{
		if ($year < 2020 || $year > 2100) {
			return null;
		}

		$owner = $appAdmin ?? $this->currentAppAdminGetter->getAppAdmin();
		$profile = $this->profileRepository->findByYear($year, $owner);
		if ($profile !== null && $profile->isClosed()) {
			return null;
		}

		$latestReport = $this->reportRepository->findLatestForYear($year, $owner);
		if ($latestReport === null) {
			return null;
		}

		$now = $this->datetimeFactory->createNow();
		$pendingReport = $this->reportRepository->findLatestPendingForYear($year, $owner);
		if ($pendingReport !== null && $pendingReport->isPending($now)) {
			return $pendingReport;
		}

		try {
			return $this->enqueue($year, $owner);
		} catch (Throwable $exception) {
			$this->logger->warning('Automatic tax year report refresh failed to enqueue', [
				'year' => $year,
				'appAdminId' => $owner->getId()->toString(),
				'exception' => $exception,
			]);

			return null;
		}
	}

	public function generateSync(int $year, AppAdmin $appAdmin): TaxYearReport
	{
		$this->validateYear($year);
		$now = $this->datetimeFactory->createNow();

		$report = new TaxYearReport($appAdmin, $year, $now, $now);
		$this->entityManager->persist($report);
		$this->entityManager->flush();

		$this->process($report->getId()->toString());

		return $report;
	}

	public function process(string $reportId): void
	{
		$report = $this->reportRepository->getByIdForProcessing(Uuid::fromString($reportId));
		$now = $this->datetimeFactory->createNow();
		if ($report->isCompleted() || ($report->isProcessing() && !$report->isStaleProcessing($now))) {
			return;
		}

		$report->markProcessing($now);
		$this->entityManager->flush();

		try {
			$snapshot = $this->reportBuilder->build($report);
			$report->complete($snapshot, $this->datetimeFactory->createNow());
			$this->entityManager->flush();
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();
			$this->logger->error('Tax year report processing failed', [
				'reportId' => $reportId,
				'exception' => $exception,
			]);

			throw $exception;
		}
	}

	public function retry(string $reportId): void
	{
		$report = $this->reportRepository->getById(Uuid::fromString($reportId));
		$now = $this->datetimeFactory->createNow();
		if (!$report->canRetry($now)) {
			return;
		}

		$report->markQueued($now);
		$this->entityManager->flush();

		try {
			$this->jobRequestFacade->addTaxYearReportProcessToQueue($report->getId()->toString());
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();

			throw $exception;
		}
	}

	private function validateYear(int $year): void
	{
		if ($year < 2020 || $year > 2100) {
			throw new InvalidArgumentException(sprintf('Tax year %d is out of supported range.', $year));
		}
	}

}
