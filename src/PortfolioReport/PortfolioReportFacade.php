<?php

declare(strict_types = 1);

namespace App\PortfolioReport;

use App\Doctrine\LockModeEnum;
use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;
use Throwable;

class PortfolioReportFacade
{

	public function __construct(
		private readonly PortfolioReportRepository $portfolioReportRepository,
		private readonly PortfolioReportPeriodResolver $portfolioReportPeriodResolver,
		private readonly PortfolioReportGenerator $portfolioReportGenerator,
		private readonly JobRequestFacade $jobRequestFacade,
		private readonly EntityManagerInterface $entityManager,
		private readonly DatetimeFactory $datetimeFactory,
	)
	{
	}

	public function requestGenerate(
		PortfolioReportPeriodTypeEnum $periodType,
		ImmutableDateTime $referenceDate,
		bool $forceRegenerate = false,
	): PortfolioReport
	{
		$period = $this->portfolioReportPeriodResolver->resolve($periodType, $referenceDate);
		$portfolioReport = $this->portfolioReportRepository->findByPeriod(
			$period->getType(),
			$period->getDateFrom(),
			$period->getDateTo(),
		);

		$now = $this->datetimeFactory->createNow();
		if ($portfolioReport === null) {
			$portfolioReport = new PortfolioReport(
				$period->getType(),
				$period->getDateFrom(),
				$period->getDateTo(),
				$now,
			);
			$this->entityManager->persist($portfolioReport);
		}

		if ($portfolioReport->getStatus() === PortfolioReportStatusEnum::PENDING || $forceRegenerate) {
			$this->jobRequestFacade->addToQueue(
				JobRequestTypeEnum::PORTFOLIO_REPORT_GENERATE,
				['id' => $portfolioReport->getId()->toString()],
			);
		}

		$this->entityManager->flush();

		return $portfolioReport;
	}

	public function generate(string $id): PortfolioReport
	{
		$now = $this->datetimeFactory->createNow();
		$portfolioReport = $this->portfolioReportRepository->getById(
			Uuid::fromString($id),
			LockModeEnum::PESSIMISTIC_WRITE,
		);
		$portfolioReport->markProcessing($now);
		$this->entityManager->flush();

		try {
			$result = $this->portfolioReportGenerator->generate($portfolioReport, $now);
			$portfolioReport->complete(
				$result->getPortfolioValueStartCzk(),
				$result->getPortfolioValueEndCzk(),
				$result->getInvestedAmountStartCzk(),
				$result->getInvestedAmountEndCzk(),
				$result->getDividendsTotalCzk(),
				$result->getGoalsProgressSummary(),
				$result->getSummaryText(),
				$result->getAiPrompt(),
				$result->getAssetPerformances(),
				$result->getDividends(),
				$result->getGoalProgressItems(),
				$now,
				$result->getSnapshot(),
			);
			$this->entityManager->flush();
		} catch (Throwable $throwable) {
			$portfolioReport->fail($throwable->getMessage(), $now);
			$this->entityManager->flush();

			throw $throwable;
		}

		return $portfolioReport;
	}

}
