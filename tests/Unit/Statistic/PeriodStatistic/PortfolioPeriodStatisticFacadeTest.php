<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\PeriodStatistic;

use App\JobRequest\JobRequestFacade;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartPointDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticBuilder;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticBuildResult;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticFacade;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticRepository;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticStatusEnum;
use App\Statistic\PortfolioStatisticRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PortfolioPeriodStatisticFacadeTest extends TestCase
{

	public function testCreateNormalizesRangePersistsReportAndAddsJobToQueue(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())
			->method('persist')
			->with(self::isInstanceOf(PortfolioPeriodStatistic::class));
		$entityManager->expects(self::once())->method('flush');
		$jobRequestFacade = $this->createMock(JobRequestFacade::class);
		$jobRequestFacade->expects(self::once())
			->method('addPortfolioPeriodStatisticProcessToQueue')
			->with(self::callback(static fn (string $id): bool => $id !== ''));

		$report = $this->createFacade(
			$entityManager,
			$jobRequestFacade,
			$this->createDatetimeFactory($now),
		)->create(
			new ImmutableDateTime('2026-06-01 14:30:00'),
			new ImmutableDateTime('2026-06-30 08:15:00'),
		);

		self::assertSame('2026-06-01 00:00:00', $report->getRequestedStartAt()->format('Y-m-d H:i:s'));
		self::assertSame('2026-06-30 23:59:59', $report->getRequestedEndAt()->format('Y-m-d H:i:s'));
		self::assertSame(PortfolioPeriodStatisticStatusEnum::QUEUED, $report->getStatus());
	}

	public function testCreateRejectsFutureRangeWithoutPersistenceOrQueue(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('persist');
		$entityManager->expects(self::never())->method('flush');
		$jobRequestFacade = $this->createMock(JobRequestFacade::class);
		$jobRequestFacade->expects(self::never())->method('addPortfolioPeriodStatisticProcessToQueue');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('cannot be in the future');

		$this->createFacade(
			$entityManager,
			$jobRequestFacade,
			$this->createDatetimeFactory($now),
		)->create(
			new ImmutableDateTime('2026-07-16'),
			new ImmutableDateTime('2026-07-17'),
		);
	}

	public function testProcessCompletesQueuedReport(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$report = $this->createReport();
		$repository = $this->createStub(PortfolioPeriodStatisticRepository::class);
		$repository->method('getById')->willReturn($report);
		$builder = $this->createMock(PortfolioPeriodStatisticBuilder::class);
		$builder->expects(self::once())->method('build')->with($report)->willReturn($this->createBuildResult());
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::exactly(2))->method('flush');

		$this->createFacade(
			$entityManager,
			$this->createStub(JobRequestFacade::class),
			$this->createDatetimeFactory($now),
			$repository,
			$builder,
		)->process($report->getId()->toString());

		self::assertTrue($report->isCompleted());
		self::assertSame(25.0, $report->getSummary()?->totalPeriodProfit);
		self::assertSame('2026-06-01', $report->getEffectiveStartAt()?->format('Y-m-d'));
		self::assertSame('2026-06-30', $report->getEffectiveEndAt()?->format('Y-m-d'));
	}

	public function testProcessMarksReportFailedAndLogsBuilderException(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$report = $this->createReport();
		$repository = $this->createStub(PortfolioPeriodStatisticRepository::class);
		$repository->method('getById')->willReturn($report);
		$exception = new RuntimeException('Builder failed');
		$builder = $this->createMock(PortfolioPeriodStatisticBuilder::class);
		$builder->expects(self::once())->method('build')->with($report)->willThrowException($exception);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::exactly(2))->method('flush');
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())
			->method('error')
			->with(
				'Portfolio period statistic processing failed',
				self::callback(static fn (array $context): bool => $context['exception'] === $exception),
			);

		$this->expectExceptionObject($exception);

		try {
			$this->createFacade(
				$entityManager,
				$this->createStub(JobRequestFacade::class),
				$this->createDatetimeFactory($now),
				$repository,
				$builder,
				$logger,
			)->process($report->getId()->toString());
		} finally {
			self::assertSame(PortfolioPeriodStatisticStatusEnum::FAILED, $report->getStatus());
			self::assertSame('Builder failed', $report->getProcessingError());
		}
	}

	public function testProcessDoesNothingForCompletedReport(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$report = $this->createReport();
		$result = $this->createBuildResult();
		$report->complete(
			$result->effectiveStartAt,
			$result->effectiveEndAt,
			$result->summary,
			$result->assetSection,
			$result->dividendSection,
			$result->chartSection,
			$now,
		);
		$repository = $this->createStub(PortfolioPeriodStatisticRepository::class);
		$repository->method('getById')->willReturn($report);
		$builder = $this->createMock(PortfolioPeriodStatisticBuilder::class);
		$builder->expects(self::never())->method('build');
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::never())->method('flush');

		$this->createFacade(
			$entityManager,
			$this->createStub(JobRequestFacade::class),
			$this->createDatetimeFactory($now),
			$repository,
			$builder,
		)->process($report->getId()->toString());
	}

	public function testRetryQueuesFailedReport(): void
	{
		$now = new ImmutableDateTime('2026-07-15 12:00:00');
		$report = $this->createReport();
		$report->markFailed('Initial failure', $now);
		$repository = $this->createStub(PortfolioPeriodStatisticRepository::class);
		$repository->method('getById')->willReturn($report);
		$entityManager = $this->createMock(EntityManagerInterface::class);
		$entityManager->expects(self::once())->method('flush');
		$jobRequestFacade = $this->createMock(JobRequestFacade::class);
		$jobRequestFacade->expects(self::once())
			->method('addPortfolioPeriodStatisticProcessToQueue')
			->with($report->getId()->toString());

		$this->createFacade(
			$entityManager,
			$jobRequestFacade,
			$this->createDatetimeFactory($now),
			$repository,
		)->retry($report->getId()->toString());

		self::assertSame(PortfolioPeriodStatisticStatusEnum::QUEUED, $report->getStatus());
		self::assertNull($report->getProcessingError());
	}

	private function createFacade(
		EntityManagerInterface $entityManager,
		JobRequestFacade $jobRequestFacade,
		DatetimeFactory $datetimeFactory,
		PortfolioPeriodStatisticRepository|null $repository = null,
		PortfolioPeriodStatisticBuilder|null $builder = null,
		LoggerInterface|null $logger = null,
	): PortfolioPeriodStatisticFacade
	{
		return new PortfolioPeriodStatisticFacade(
			$repository ?? $this->createStub(PortfolioPeriodStatisticRepository::class),
			$this->createStub(PortfolioStatisticRecordRepository::class),
			$builder ?? $this->createStub(PortfolioPeriodStatisticBuilder::class),
			$jobRequestFacade,
			$entityManager,
			$datetimeFactory,
			$logger ?? $this->createStub(LoggerInterface::class),
		);
	}

	private function createDatetimeFactory(ImmutableDateTime $now): DatetimeFactory
	{
		$datetimeFactory = $this->createStub(DatetimeFactory::class);
		$datetimeFactory->method('createNow')->willReturn($now);
		return $datetimeFactory;
	}

	private function createReport(): PortfolioPeriodStatistic
	{
		return new PortfolioPeriodStatistic(
			new ImmutableDateTime('2026-06-01 00:00:00'),
			new ImmutableDateTime('2026-06-30 23:59:59'),
			new ImmutableDateTime('2026-07-01 12:00:00'),
		);
	}

	private function createBuildResult(): PortfolioPeriodStatisticBuildResult
	{
		return new PortfolioPeriodStatisticBuildResult(
			new ImmutableDateTime('2026-06-01 10:00:00'),
			new ImmutableDateTime('2026-06-30 20:00:00'),
			new PortfolioPeriodStatisticSummaryDTO(
				100.0,
				120.0,
				20.0,
				150.0,
				180.0,
				30.0,
				20.0,
				10.0,
				5.0,
				10.0,
				25.0,
				6.67,
				null,
				6.5,
				6.4,
			),
			new PortfolioPeriodStatisticAssetSectionDTO([]),
			new PortfolioPeriodStatisticDividendSectionDTO(0, 0.0, 0.0, 0.0, []),
			new PortfolioPeriodStatisticChartSectionDTO(
				[new PortfolioPeriodStatisticChartPointDTO('2026-06-01', 150.0)],
				[new PortfolioPeriodStatisticChartPointDTO('2026-06-01', 100.0)],
				[],
			),
		);
	}

}
