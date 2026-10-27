<?php

declare(strict_types = 1);

namespace App\Work\Report;

use App\Cash\Income\WorkMonthlyIncome\WorkMonthlyIncomeOwnerProvider;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Psr\Log\LoggerInterface;

class WorkReportFacade
{

	public function __construct(
		private readonly WorkMonthlyIncomeOwnerProvider $ownerProvider,
		private readonly WorkReportBuilder $builder,
		private readonly WorkReportRepository $repository,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
		private readonly LoggerInterface $logger,
		private readonly WorkReportAiSummaryGenerator $summaryGenerator,
		private readonly WorkReportSanitizer $sanitizer,
	)
	{
	}

	public function generate(WorkReportPeriod $period, bool $withAi = true): WorkReport
	{
		$this->logger->info('Generating work report', [
			'reportDate' => $period->reportDate->format('Y-m-d'),
			'from' => $period->from->format('Y-m-d'),
			'to' => $period->to->format('Y-m-d'),
		]);
		$owner = $this->ownerProvider->getOwner();
		$now = $this->datetimeFactory->createNow();
		$data = $this->builder->build($period, $now);
		$report = $this->repository->getByReportDateForAppAdmin($period->reportDate, $owner);
		$this->logger->info('Saving work report', ['operation' => $report === null ? 'create' : 'update']);
		if ($report === null) {
			$report = new WorkReport($owner, $period->reportDate, $data, $now);
			$this->entityManager->persist($report);
		} else {
			$report->update($data, $now);
		}

		$this->entityManager->flush();
		if ($withAi) {
			$this->addSummary($report);
		}

		return $report;
	}

	public function summarize(ImmutableDateTime $reportDate): WorkReport
	{
		$report = $this->repository->getByReportDateForAppAdmin($reportDate, $this->ownerProvider->getOwner());
		if ($report === null) {
			throw new WorkReportException('No saved work report exists for this date. Generate its source data first.');
		}

		$this->addSummary($report);
		return $report;
	}

	private function addSummary(WorkReport $report): void
	{
		$data = $this->sanitizer->sanitizeData($report->getData());
		$data['aiSummary'] = $this->summaryGenerator->generate($data);
		$report->update($data, $this->datetimeFactory->createNow());
		$this->entityManager->flush();
	}

}
