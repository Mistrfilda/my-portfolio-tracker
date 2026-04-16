<?php

declare(strict_types = 1);

namespace App\Test\Unit\PortfolioReport;

use App\JobRequest\JobRequestFacade;
use App\JobRequest\JobRequestTypeEnum;
use App\PortfolioReport\PortfolioReport;
use App\PortfolioReport\PortfolioReportFacade;
use App\PortfolioReport\PortfolioReportGenerator;
use App\PortfolioReport\PortfolioReportPeriod;
use App\PortfolioReport\PortfolioReportPeriodResolver;
use App\PortfolioReport\PortfolioReportPeriodTypeEnum;
use App\PortfolioReport\PortfolioReportRepository;
use App\Test\UpdatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\DatetimeFactory;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Mockery;

class PortfolioReportFacadeTest extends UpdatedTestCase
{

	private PortfolioReportFacade $portfolioReportFacade;

	private PortfolioReportRepository $portfolioReportRepository;

	private PortfolioReportPeriodResolver $portfolioReportPeriodResolver;

	private JobRequestFacade $jobRequestFacade;

	private EntityManagerInterface $entityManager;

	private DatetimeFactory $datetimeFactory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->portfolioReportRepository = self::createMockWithIgnoreMethods(PortfolioReportRepository::class);
		$this->portfolioReportPeriodResolver = self::createMockWithIgnoreMethods(PortfolioReportPeriodResolver::class);
		$this->jobRequestFacade = self::createMockWithIgnoreMethods(JobRequestFacade::class);
		$this->entityManager = self::createMockWithIgnoreMethods(EntityManagerInterface::class);
		$this->datetimeFactory = self::createMockWithIgnoreMethods(DatetimeFactory::class);

		$this->portfolioReportFacade = new PortfolioReportFacade(
			$this->portfolioReportRepository,
			$this->portfolioReportPeriodResolver,
			self::createMockWithIgnoreMethods(PortfolioReportGenerator::class),
			$this->jobRequestFacade,
			$this->entityManager,
			$this->datetimeFactory,
		);
	}

	public function testRequestGenerateCreatesPendingReportAndQueuesJob(): void
	{
		$referenceDate = new ImmutableDateTime('2026-04-13 12:00:00');
		$now = new ImmutableDateTime('2026-04-13 13:00:00');
		$period = new PortfolioReportPeriod(
			PortfolioReportPeriodTypeEnum::MONTHLY,
			new ImmutableDateTime('2026-04-01 00:00:01'),
			new ImmutableDateTime('2026-04-30 23:59:59'),
			$referenceDate,
		);

		$this->portfolioReportPeriodResolver->shouldReceive('resolve')
			->once()
			->with(PortfolioReportPeriodTypeEnum::MONTHLY, $referenceDate)
			->andReturn($period);

		$this->portfolioReportRepository->shouldReceive('findByPeriod')
			->once()
			->with(
				PortfolioReportPeriodTypeEnum::MONTHLY,
				$period->getDateFrom(),
				$period->getDateTo(),
			)
			->andReturn(null);

		$this->datetimeFactory->shouldReceive('createNow')
			->once()
			->andReturn($now);

		$this->entityManager->shouldReceive('persist')
			->once()
			->withArgs(static fn (PortfolioReport $portfolioReport): bool =>
				$portfolioReport->getPeriodType() === PortfolioReportPeriodTypeEnum::MONTHLY
				&& $portfolioReport->getStatus()->value === 'pending');

		$this->jobRequestFacade->shouldReceive('addToQueue')
			->once()
			->with(
				JobRequestTypeEnum::PORTFOLIO_REPORT_GENERATE,
				Mockery::on(
					static fn (array $payload): bool => isset($payload['id']) && is_string(
						$payload['id'],
					) && $payload['id'] !== '',
				),
			);

		$this->entityManager->shouldReceive('flush')
			->once();

		$result = $this->portfolioReportFacade->requestGenerate(
			PortfolioReportPeriodTypeEnum::MONTHLY,
			$referenceDate,
		);

		$this->assertSame(PortfolioReportPeriodTypeEnum::MONTHLY, $result->getPeriodType());
		$this->assertSame('2026-04-01 00:00:01', $result->getDateFrom()->format('Y-m-d H:i:s'));
	}

}
