<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic\Performance;

use App\Statistic\Performance\PortfolioPerformanceCalculator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioPerformanceCalculatorTest extends TestCase
{

	private PortfolioPerformanceCalculator $calculator;

	protected function setUp(): void
	{
		$this->calculator = new PortfolioPerformanceCalculator();
	}

	public function testCarriesSaleProceedsAndReinvestsThemWithoutNewContribution(): void
	{
		$saleMonth = $this->calculator->calculateMonth(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			100.0,
			0.0,
			100.0,
			0.0,
			50.0,
			0.0,
			0.0,
			new ImmutableDateTime('2026-03-01'),
		);

		self::assertSame(0.0, $saleMonth->getExternalContribution());
		self::assertSame(150.0, $saleMonth->getCashAtEnd());
		self::assertEqualsWithDelta(1.5, $saleMonth->getReturnFactor(), 0.0001);

		$reinvestmentMonth = $this->calculator->calculateMonth(
			new ImmutableDateTime('2026-01-31'),
			new ImmutableDateTime('2026-02-28'),
			0.0,
			150.0,
			0.0,
			180.0,
			0.0,
			0.0,
			$saleMonth->getCashAtEnd(),
			new ImmutableDateTime('2026-03-01'),
		);

		self::assertSame(0.0, $reinvestmentMonth->getExternalContribution());
		self::assertSame(0.0, $reinvestmentMonth->getCashAtEnd());
		self::assertEqualsWithDelta(1.2, $reinvestmentMonth->getReturnFactor(), 0.0001);

		$summary = $this->calculator->calculateSummary([$saleMonth, $reinvestmentMonth]);
		self::assertNotNull($summary);
		self::assertEqualsWithDelta(80.0, $summary->getTimeWeightedReturn(), 0.0001);
	}

	public function testKeepsUninvestedDividendAsPortfolioCash(): void
	{
		$month = $this->calculator->calculateMonth(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			100.0,
			100.0,
			100.0,
			90.0,
			0.0,
			10.0,
			0.0,
			new ImmutableDateTime('2026-02-01'),
		);

		self::assertSame(0.0, $month->getExternalContribution());
		self::assertSame(10.0, $month->getCashAtEnd());
		self::assertSame(100.0, $month->getAccountValueAtEnd());
		self::assertEqualsWithDelta(1.0, $month->getReturnFactor(), 0.0001);
	}

	public function testUsesOnlyUncoveredFundingNeedAsExternalContribution(): void
	{
		$month = $this->calculator->calculateMonth(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			100.0,
			150.0,
			100.0,
			160.0,
			0.0,
			0.0,
			0.0,
			new ImmutableDateTime('2026-02-01'),
		);

		self::assertSame(50.0, $month->getExternalContribution());
		self::assertSame(0.0, $month->getCashAtEnd());
		self::assertEqualsWithDelta(1.08, $month->getReturnFactor(), 0.0001);
	}

	public function testTreatsSubCrownFundingDifferenceAsRoundingNoise(): void
	{
		$month = $this->calculator->calculateMonth(
			new ImmutableDateTime('2026-01-01'),
			new ImmutableDateTime('2026-01-31'),
			100.0,
			100.5,
			100.0,
			100.0,
			0.0,
			0.0,
			0.0,
			new ImmutableDateTime('2026-02-01'),
		);

		self::assertSame(0.0, $month->getExternalContribution());
		self::assertSame(0.0, $month->getCashAtEnd());
	}

}
