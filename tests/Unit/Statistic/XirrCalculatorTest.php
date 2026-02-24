<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Statistic\Total\XirrCalculator;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class XirrCalculatorTest extends TestCase
{

	// -------------------------------------------------------------------------
	// Základní testy XIRR výpočtu
	// -------------------------------------------------------------------------

	/**
	 * Test "All time" z Excel tabulky:
	 * 15 měsíčních vkladů po -10000, poslední hodnota 162000
	 * Očekávaný XIRR: 0.2585295518 (25.85%)
	 */
	public function testXirrAllTime(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2022-01-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-02-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-03-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-04-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-05-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-06-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-07-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-08-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-09-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-10-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-11-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-12-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-03-01'), 'amount' => 162000.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(0.2585295518, $result, 0.001);
	}

	/**
	 * Test "Posledni rok" z Excel tabulky:
	 * Vklady od 2022-03-01, -30000 jako první (sečtení předchozích investic)
	 * Očekávaný XIRR: 0.2672536388 (26.73%)
	 */
	public function testXirrLastYear(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2022-03-01'), 'amount' => -30000.0],
			['date' => new ImmutableDateTime('2022-04-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-05-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-06-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-07-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-08-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-09-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-10-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-11-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2022-12-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-03-01'), 'amount' => 162000.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(0.2672536388, $result, 0.001);
	}

	/**
	 * Test "Posledni 3 mesice" z Excel tabulky:
	 * -120000, -10000, -10000, 162000
	 * Očekávaný XIRR: 0.8910827559 (89.1%)
	 */
	public function testXirrLast3Months(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2022-12-01'), 'amount' => -120000.0],
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-03-01'), 'amount' => 162000.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(0.8910827559, $result, 0.01);
	}

	/**
	 * Test "Posledni mesic" z Excel tabulky:
	 * -140000, 162000
	 * Očekávaný XIRR: 5.703390907 (570.3%)
	 */
	public function testXirrLastMonth(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => -140000.0],
			['date' => new ImmutableDateTime('2023-03-01'), 'amount' => 162000.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(5.703390907, $result, 0.01);
	}

	// -------------------------------------------------------------------------
	// adjustForPeriod - přepočet na období
	// -------------------------------------------------------------------------

	/**
	 * Test přepočtu XIRR na období dle Excel vzorce:
	 * Posledni rok: (1+0.2672536388)^((2023-03-01 - 2022-03-01)/365)-1 = 0.2672536388
	 */
	public function testAdjustForPeriodLastYear(): void
	{
		$result = XirrCalculator::adjustForPeriod(0.2672536388, 365);
		self::assertEqualsWithDelta(0.2672536388, $result, 0.001);
	}

	/**
	 * Test přepočtu XIRR na období dle Excel vzorce:
	 * Posledni 3 mesice: (1+0.8910827559)^((2023-03-01 - 2022-12-01)/365)-1 = 0.1701189016
	 */
	public function testAdjustForPeriodLast3Months(): void
	{
		// 2022-12-01 to 2023-03-01 = 90 dní
		$result = XirrCalculator::adjustForPeriod(0.8910827559, 90);
		self::assertEqualsWithDelta(0.1701189016, $result, 0.001);
	}

	/**
	 * Test přepočtu XIRR na období dle Excel vzorce:
	 * Posledni mesic: (1+5.703390907)^((2023-03-01 - 2023-02-01)/365)-1 = 0.1571428571
	 */
	public function testAdjustForPeriodLastMonth(): void
	{
		// 2023-02-01 to 2023-03-01 = 28 dní
		$result = XirrCalculator::adjustForPeriod(5.703390907, 28);
		self::assertEqualsWithDelta(0.1571428571, $result, 0.001);
	}

	public function testAdjustForPeriodZeroDays(): void
	{
		$result = XirrCalculator::adjustForPeriod(0.25, 0);
		self::assertSame(0.0, $result);
	}

	// -------------------------------------------------------------------------
	// Okrajové případy
	// -------------------------------------------------------------------------

	public function testCalculateReturnsNullForEmptyArray(): void
	{
		$result = XirrCalculator::calculate([]);
		self::assertNull($result);
	}

	public function testCalculateReturnsNullForSingleCashFlow(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => -10000.0],
		];
		$result = XirrCalculator::calculate($cashFlows);
		self::assertNull($result);
	}

	public function testCalculateReturnsNullForAllNegativeCashFlows(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => -10000.0],
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => -5000.0],
		];
		$result = XirrCalculator::calculate($cashFlows);
		self::assertNull($result);
	}

	public function testCalculateReturnsNullForAllPositiveCashFlows(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => 10000.0],
			['date' => new ImmutableDateTime('2023-02-01'), 'amount' => 5000.0],
		];
		$result = XirrCalculator::calculate($cashFlows);
		self::assertNull($result);
	}

	/**
	 * Test jednoduchého příkladu: investice -100, za rok +110 → výnos 10%
	 */
	public function testCalculateSimple10PercentReturn(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2022-01-01'), 'amount' => -100.0],
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => 110.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(0.10, $result, 0.001);
	}

	/**
	 * Test záporného výnosu: investice -100, za rok +90 → výnos -10%
	 */
	public function testCalculateNegativeReturn(): void
	{
		$cashFlows = [
			['date' => new ImmutableDateTime('2022-01-01'), 'amount' => -100.0],
			['date' => new ImmutableDateTime('2023-01-01'), 'amount' => 90.0],
		];

		$result = XirrCalculator::calculate($cashFlows);
		self::assertNotNull($result);
		self::assertEqualsWithDelta(-0.10, $result, 0.001);
	}

}
