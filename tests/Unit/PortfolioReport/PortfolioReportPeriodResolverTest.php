<?php

declare(strict_types = 1);

namespace App\Test\Unit\PortfolioReport;

use App\PortfolioReport\PortfolioReportPeriodResolver;
use App\PortfolioReport\PortfolioReportPeriodTypeEnum;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioReportPeriodResolverTest extends TestCase
{

	private PortfolioReportPeriodResolver $portfolioReportPeriodResolver;

	protected function setUp(): void
	{
		parent::setUp();
		$this->portfolioReportPeriodResolver = new PortfolioReportPeriodResolver();
	}

	public function testResolveDailyPeriod(): void
	{
		$period = $this->portfolioReportPeriodResolver->resolve(
			PortfolioReportPeriodTypeEnum::DAILY,
			new ImmutableDateTime('2026-04-13 18:30:00'),
		);

		$this->assertSame('2026-04-13 00:00:01', $period->getDateFrom()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-04-13 23:59:59', $period->getDateTo()->format('Y-m-d H:i:s'));
	}

	public function testResolveWeeklyPeriodStartsOnMondayAndEndsOnSunday(): void
	{
		$period = $this->portfolioReportPeriodResolver->resolve(
			PortfolioReportPeriodTypeEnum::WEEKLY,
			new ImmutableDateTime('2026-04-15 18:30:00'),
		);

		$this->assertSame('2026-04-13 00:00:01', $period->getDateFrom()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-04-19 23:59:59', $period->getDateTo()->format('Y-m-d H:i:s'));
	}

	public function testResolveMonthlyPeriod(): void
	{
		$period = $this->portfolioReportPeriodResolver->resolve(
			PortfolioReportPeriodTypeEnum::MONTHLY,
			new ImmutableDateTime('2026-02-10 18:30:00'),
		);

		$this->assertSame('2026-02-01 00:00:01', $period->getDateFrom()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-02-28 23:59:59', $period->getDateTo()->format('Y-m-d H:i:s'));
	}

	public function testResolveBimonthlyPeriodForEvenMonth(): void
	{
		$period = $this->portfolioReportPeriodResolver->resolve(
			PortfolioReportPeriodTypeEnum::BIMONTHLY,
			new ImmutableDateTime('2026-04-10 18:30:00'),
		);

		$this->assertSame('2026-03-01 00:00:01', $period->getDateFrom()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-04-30 23:59:59', $period->getDateTo()->format('Y-m-d H:i:s'));
	}

	public function testResolveBimonthlyPeriodForOddMonth(): void
	{
		$period = $this->portfolioReportPeriodResolver->resolve(
			PortfolioReportPeriodTypeEnum::BIMONTHLY,
			new ImmutableDateTime('2026-03-10 18:30:00'),
		);

		$this->assertSame('2026-03-01 00:00:01', $period->getDateFrom()->format('Y-m-d H:i:s'));
		$this->assertSame('2026-04-30 23:59:59', $period->getDateTo()->format('Y-m-d H:i:s'));
	}

}
