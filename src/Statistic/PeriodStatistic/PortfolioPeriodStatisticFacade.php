<?php

declare(strict_types = 1);

namespace App\Statistic\PeriodStatistic;

use App\JobRequest\JobRequestFacade;
use App\Statistic\PortfolioStatisticRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

class PortfolioPeriodStatisticFacade
{

	public function __construct(
		private PortfolioPeriodStatisticRepository $portfolioPeriodStatisticRepository,
		private PortfolioStatisticRecordRepository $portfolioStatisticRecordRepository,
		private PortfolioPeriodStatisticBuilder $portfolioPeriodStatisticBuilder,
		private JobRequestFacade $jobRequestFacade,
		private EntityManagerInterface $entityManager,
		private DatetimeFactory $datetimeFactory,
		private LoggerInterface $logger,
	)
	{
	}

	public function create(
		ImmutableDateTime $requestedStartAt,
		ImmutableDateTime $requestedEndAt,
	): PortfolioPeriodStatistic
	{
		$startAt = $requestedStartAt->setTime(0, 0, 0);
		$endAt = $requestedEndAt->setTime(23, 59, 59);
		$this->validateRange($startAt, $endAt);

		$now = $this->datetimeFactory->createNow();
		$report = new PortfolioPeriodStatistic($startAt, $endAt, $now);
		$this->entityManager->persist($report);
		$this->entityManager->flush();

		try {
			$this->jobRequestFacade->addPortfolioPeriodStatisticProcessToQueue($report->getId()->toString());
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();

			throw $exception;
		}

		return $report;
	}

	public function createForPreset(PortfolioPeriodStatisticPresetEnum $preset): PortfolioPeriodStatistic
	{
		$now = $this->datetimeFactory->createNow();
		$numberOfDays = $preset->getNumberOfDays();
		if ($numberOfDays !== null) {
			return $this->create($now->deductDaysFromDatetime($numberOfDays), $now);
		}

		if ($preset === PortfolioPeriodStatisticPresetEnum::YEAR_TO_DATE) {
			return $this->create($now->setDate($now->getYear(), 1, 1), $now);
		}

		$firstRecord = $this->portfolioStatisticRecordRepository->findFirst();
		if ($firstRecord === null) {
			throw new InvalidArgumentException('No portfolio statistic snapshot is available.');
		}

		return $this->create($firstRecord->getCreatedAt(), $now);
	}

	public function get(string $id): PortfolioPeriodStatistic
	{
		return $this->portfolioPeriodStatisticRepository->getById(Uuid::fromString($id));
	}

	public function process(string $id): void
	{
		$report = $this->get($id);
		if ($report->isCompleted() || $report->isProcessing()) {
			return;
		}

		$report->markProcessing($this->datetimeFactory->createNow());
		$this->entityManager->flush();

		try {
			$result = $this->portfolioPeriodStatisticBuilder->build($report);
			$report->complete(
				$result->effectiveStartAt,
				$result->effectiveEndAt,
				$result->summary,
				$result->assetSection,
				$result->dividendSection,
				$result->chartSection,
				$this->datetimeFactory->createNow(),
			);
			$this->entityManager->flush();
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();
			$this->logger->error('Portfolio period statistic processing failed', [
				'reportId' => $id,
				'exception' => $exception,
			]);

			throw $exception;
		}
	}

	public function retry(string $id): void
	{
		$report = $this->get($id);
		if (!$report->canRetry()) {
			return;
		}

		$report->markQueued($this->datetimeFactory->createNow());
		$this->entityManager->flush();

		try {
			$this->jobRequestFacade->addPortfolioPeriodStatisticProcessToQueue($report->getId()->toString());
		} catch (Throwable $exception) {
			$report->markFailed($exception->getMessage(), $this->datetimeFactory->createNow());
			$this->entityManager->flush();

			throw $exception;
		}
	}

	public function regenerate(string $id): PortfolioPeriodStatistic
	{
		$report = $this->get($id);
		return $this->create($report->getRequestedStartAt(), $report->getRequestedEndAt());
	}

	private function validateRange(ImmutableDateTime $startAt, ImmutableDateTime $endAt): void
	{
		if ($startAt >= $endAt) {
			throw new InvalidArgumentException('The start date must be before the end date.');
		}

		$today = $this->datetimeFactory->createNow()->setTime(23, 59, 59);
		if ($startAt > $today || $endAt > $today) {
			throw new InvalidArgumentException('The selected period cannot be in the future.');
		}
	}

}
