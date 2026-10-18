<?php

declare(strict_types = 1);

namespace App\Test\Integration\Statistic\Performance;

use App\Admin\AppAdmin;
use App\Admin\CurrentAppAdminGetter;
use App\Statistic\Performance\PortfolioPerformanceMonth;
use App\Statistic\Performance\PortfolioPerformanceMonthRepository;
use App\Test\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use Ramsey\Uuid\Uuid;

class PortfolioPerformanceMonthRepositoryTest extends IntegrationTestCase
{

	private AppAdmin $appAdmin;

	protected function setUp(): void
	{
		parent::setUp();
		$em = $this->getService(EntityManagerInterface::class);
		$name = 'statistic-' . Uuid::uuid4()->toString();
		$this->appAdmin = new AppAdmin(
			$name,
			$name,
			$name . '@example.test',
			'unused',
			new ImmutableDateTime('2026-01-01'),
			false,
			false,
		);
		$em->persist($this->appAdmin);
		$em->flush();
		$this->getService(CurrentAppAdminGetter::class)->setApiAppAdmin($this->appAdmin);
	}

	public function testFindsMonthsInOrderAndLastMonthBeforeDate(): void
	{
		$entityManager = $this->getService(EntityManagerInterface::class);
		$repository = $this->getService(PortfolioPerformanceMonthRepository::class);
		$first = $this->createMonth('2098-01-31', 1.05);
		$second = $this->createMonth('2098-02-28', 1.10);
		$entityManager->persist($second);
		$entityManager->persist($first);
		$entityManager->flush();

		$months = array_values(array_filter(
			$repository->findAllOrdered(),
			static fn (PortfolioPerformanceMonth $month): bool =>
				$month->getPeriodEndAt()->format('Y') === '2098',
		));

		self::assertSame(['2098-01-31', '2098-02-28'], array_map(
			static fn (PortfolioPerformanceMonth $month): string =>
				$month->getPeriodEndAt()->format('Y-m-d'),
			$months,
		));
		self::assertSame(
			'2098-01-31',
			$repository->findLastEndingAtOrBefore(
				new ImmutableDateTime('2098-02-10'),
			)?->getPeriodEndAt()->format('Y-m-d'),
		);
	}

	private function createMonth(string $endDate, float $returnFactor): PortfolioPerformanceMonth
	{
		$end = new ImmutableDateTime($endDate);
		$start = $end->deductDaysFromDatetime(28);
		$now = new ImmutableDateTime('2098-03-01');

		return new PortfolioPerformanceMonth(
			$this->appAdmin,
			new ImmutableDateTime($end->format('Y-m-01')),
			$start,
			$end,
			100.0,
			100.0,
			100.0,
			100.0 * $returnFactor,
			0.0,
			0.0,
			0.0,
			0.0,
			0.0,
			$returnFactor,
			$now,
		);
	}

}
