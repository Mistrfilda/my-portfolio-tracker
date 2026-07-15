<?php

declare(strict_types = 1);

namespace App\Test\Integration\Statistic\PeriodStatistic;

use App\Doctrine\NoEntityFoundException;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticAssetSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartPointDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticChartSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticDividendSectionDTO;
use App\Statistic\PeriodStatistic\DTO\PortfolioPeriodStatisticSummaryDTO;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatistic;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticRepository;
use App\Statistic\PeriodStatistic\PortfolioPeriodStatisticStatusEnum;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class PortfolioPeriodStatisticRepositoryTest extends IntegrationTestCase
{

	private PortfolioPeriodStatisticRepository $repository;

	private EntityManagerInterface $entityManager;

	protected function setUp(): void
	{
		parent::setUp();

		$this->repository = $this->getService(PortfolioPeriodStatisticRepository::class);
		$this->entityManager = $this->getService(EntityManagerInterface::class);
	}

	public function testPersistsAndHydratesCompletedTypedSections(): void
	{
		$report = new PortfolioPeriodStatistic(
			new ImmutableDateTime('2026-06-01 00:00:00'),
			new ImmutableDateTime('2026-06-30 23:59:59'),
			new ImmutableDateTime('2026-07-01 08:00:00'),
		);
		$report->markProcessing(new ImmutableDateTime('2026-07-01 08:01:00'));
		$report->complete(
			new ImmutableDateTime('2026-06-02 10:00:00'),
			new ImmutableDateTime('2026-06-29 20:00:00'),
			new PortfolioPeriodStatisticSummaryDTO(
				100_000.0,
				115_000.0,
				15_000.0,
				120_000.0,
				145_000.0,
				25_000.0,
				20.83,
				10_000.0,
				1_500.0,
				2_500.0,
				14_000.0,
				8.25,
				null,
				7.75,
				7.5,
				['Start date moved to first available snapshot.'],
				true,
			),
			new PortfolioPeriodStatisticAssetSectionDTO([
				new PortfolioPeriodStatisticAssetDTO(
					'stock-id',
					'stock',
					'Test Company',
					'TEST',
					'USD',
					'2026-06-02',
					'2026-06-29',
					100.0,
					110.0,
					10.0,
					50_000.0,
					57_000.0,
					5_000.0,
					0.0,
					2_000.0,
					500.0,
					2_500.0,
				),
			]),
			new PortfolioPeriodStatisticDividendSectionDTO(
				1,
				600.0,
				500.0,
				100.0,
				[
					new PortfolioPeriodStatisticDividendDTO(
						'dividend-id',
						'stock-id',
						'Test Company',
						'TEST',
						'2026-06-10',
						'2026-06-20',
						'cash',
						10,
						'USD',
						25.0,
						20.0,
						600.0,
						500.0,
					),
				],
			),
			new PortfolioPeriodStatisticChartSectionDTO(
				[new PortfolioPeriodStatisticChartPointDTO('2026-06-02', 120_000.0)],
				[new PortfolioPeriodStatisticChartPointDTO('2026-06-02', 100_000.0)],
				[new PortfolioPeriodStatisticChartPointDTO('Test Company', 500.0)],
			),
			new ImmutableDateTime('2026-07-01 08:02:00'),
		);

		$id = $report->getId();
		$this->entityManager->persist($report);
		$this->entityManager->flush();
		$this->entityManager->clear();

		$loaded = $this->repository->getById($id);

		self::assertSame(PortfolioPeriodStatisticStatusEnum::COMPLETED, $loaded->getStatus());
		self::assertSame('2026-06-02 10:00:00', $loaded->getEffectiveStartAt()?->format('Y-m-d H:i:s'));
		self::assertSame('2026-06-29 20:00:00', $loaded->getEffectiveEndAt()?->format('Y-m-d H:i:s'));
		self::assertSame('2026-07-01 08:02:00', $loaded->getProcessingFinishedAt()?->format('Y-m-d H:i:s'));
		self::assertSame(14_000.0, $loaded->getSummary()?->totalPeriodProfit);
		self::assertTrue($loaded->getSummary()?->partial);
		self::assertSame('Test Company', $loaded->getAssetSection()?->assets[0]->name);
		self::assertSame('2026-06-10', $loaded->getDividendSection()?->dividends[0]->getExDate()->format('Y-m-d'));
		self::assertSame(500.0, $loaded->getDividendSection()?->netTotalCzk);
		self::assertSame(120_000.0, $loaded->getChartSection()?->portfolioValues[0]->value);
	}

	public function testGetByIdThrowsWhenReportDoesNotExist(): void
	{
		$this->expectException(NoEntityFoundException::class);

		$this->repository->getById(Uuid::uuid4());
	}

}
