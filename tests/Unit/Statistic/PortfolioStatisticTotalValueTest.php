<?php

declare(strict_types = 1);

namespace App\Test\Unit\Statistic;

use App\Statistic\Total\PortfolioStatisticTotalValue;
use Mistrfilda\Datetime\Types\ImmutableDateTime;
use PHPUnit\Framework\TestCase;

class PortfolioStatisticTotalValueTest extends TestCase
{

	// -------------------------------------------------------------------------
	// Pomocné metody
	// -------------------------------------------------------------------------

	private function createValue(
		float $investedAtStart,
		float $investedAtEnd,
		float $valueAtStart,
		float $valueAtEnd,
		float $closedPositionsProfit = 0.0,
		float $dividends = 0.0,
		ImmutableDateTime|null $startDate = null,
		ImmutableDateTime|null $endDate = null,
		array|null $cashFlowData = null,
	): PortfolioStatisticTotalValue
	{
		return new PortfolioStatisticTotalValue(
			month: null,
			label: 'Test',
			investedAtStart: $investedAtStart,
			investedAtEnd: $investedAtEnd,
			valueAtStart: $valueAtStart,
			valueAtEnd: $valueAtEnd,
			closedPositionsProfitInPeriod: $closedPositionsProfit,
			dividendsInPeriod: $dividends,
			startDate: $startDate,
			endDate: $endDate,
			cashFlowData: $cashFlowData,
		);
	}

	// -------------------------------------------------------------------------
	// getTotalProfit
	// -------------------------------------------------------------------------

	public function testGetTotalProfitPositive(): void
	{
		// Investováno 100k, hodnota 120k → zisk 20k
		$value = $this->createValue(
			investedAtStart: 80_000,
			investedAtEnd: 100_000,
			valueAtStart: 90_000,
			valueAtEnd: 120_000,
		);

		self::assertSame(20_000.0, $value->getTotalProfit());
	}

	public function testGetTotalProfitNegative(): void
	{
		// Investováno 100k, hodnota 80k → ztráta 20k
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 80_000,
		);

		self::assertSame(-20_000.0, $value->getTotalProfit());
	}

	public function testGetTotalProfitZero(): void
	{
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 100_000,
		);

		self::assertSame(0.0, $value->getTotalProfit());
	}

	// -------------------------------------------------------------------------
	// getPeriodProfit
	// -------------------------------------------------------------------------

	public function testGetPeriodProfitWithoutNewInvestments(): void
	{
		// Bez nových vkladů: hodnota vzrostla z 100k na 110k → zisk 10k
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
		);

		self::assertSame(10_000.0, $value->getPeriodProfit());
	}

	public function testGetPeriodProfitWithNewInvestments(): void
	{
		// Přidáno 20k, hodnota vzrostla o 25k → čistý zisk 5k
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 125_000,
		);

		// valueDiff = 25k, investedDiff = 20k → periodProfit = 5k
		self::assertSame(5_000.0, $value->getPeriodProfit());
	}

	public function testGetPeriodProfitNegative(): void
	{
		// Hodnota klesla, nové vklady nepomohly
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 110_000,
			valueAtStart: 100_000,
			valueAtEnd: 105_000,
		);

		// valueDiff = 5k, investedDiff = 10k → periodProfit = -5k
		self::assertSame(-5_000.0, $value->getPeriodProfit());
	}

	public function testGetDiffAmountIsAliasForPeriodProfit(): void
	{
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
		);

		self::assertSame($value->getPeriodProfit(), $value->getDiffAmount());
	}

	// -------------------------------------------------------------------------
	// getPeriodProfitWith*
	// -------------------------------------------------------------------------

	public function testGetPeriodProfitWithClosedPositionsAndDividends(): void
	{
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 105_000,
			closedPositionsProfit: 2_000,
			dividends: 1_500,
		);

		// periodProfit = 5k, closed = 2k, dividends = 1.5k → 8.5k
		self::assertSame(8_500.0, $value->getPeriodProfitWithClosedPositionsAndDividends());
		self::assertSame(7_000.0, $value->getPeriodProfitWithClosedPositions());
		self::assertSame(6_500.0, $value->getPeriodProfitWithDividends());
	}

	// -------------------------------------------------------------------------
	// getTotalPerformancePercentage
	// -------------------------------------------------------------------------

	public function testGetTotalPerformancePercentage(): void
	{
		// Investováno 100k, hodnota 110k → 10%
		$value = $this->createValue(
			investedAtStart: 0,
			investedAtEnd: 100_000,
			valueAtStart: 0,
			valueAtEnd: 110_000,
		);

		self::assertEqualsWithDelta(10.0, $value->getTotalPerformancePercentage(), 0.0001);
	}

	public function testGetTotalPerformancePercentageZeroInvested(): void
	{
		// Žádné investice → 0%
		$value = $this->createValue(
			investedAtStart: 0,
			investedAtEnd: 0,
			valueAtStart: 0,
			valueAtEnd: 0,
		);

		self::assertSame(0.0, $value->getTotalPerformancePercentage());
	}

	// -------------------------------------------------------------------------
	// getTimeWeightedReturn
	// -------------------------------------------------------------------------

	public function testGetTimeWeightedReturnNoNewInvestments(): void
	{
		// Hodnota vzrostla z 100k na 110k, bez nových vkladů → TWR 10%
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
		);

		self::assertEqualsWithDelta(10.0, $value->getTimeWeightedReturn(), 0.0001);
	}

	public function testGetTimeWeightedReturnWithNewInvestments(): void
	{
		// valueAtStart = 100k, nové investice = 20k → startingCapital = 120k
		// valueAtEnd = 126k → TWR = (126k/120k - 1) * 100 = 5%
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 126_000,
		);

		self::assertEqualsWithDelta(5.0, $value->getTimeWeightedReturn(), 0.0001);
	}

	public function testGetTimeWeightedReturnNegative(): void
	{
		// Hodnota klesla z 100k na 90k → TWR -10%
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 90_000,
		);

		self::assertEqualsWithDelta(-10.0, $value->getTimeWeightedReturn(), 0.0001);
	}

	public function testGetTimeWeightedReturnZeroStartingCapital(): void
	{
		// Žádný kapitál na začátku → 0
		$value = $this->createValue(
			investedAtStart: 0,
			investedAtEnd: 0,
			valueAtStart: 0,
			valueAtEnd: 0,
		);

		self::assertSame(0.0, $value->getTimeWeightedReturn());
	}

	// -------------------------------------------------------------------------
	// getAnnualizedTwr
	// -------------------------------------------------------------------------

	public function testGetAnnualizedTwrNullWithoutDates(): void
	{
		// Bez datumů → null
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
		);

		self::assertNull($value->getAnnualizedTwr());
	}

	public function testGetAnnualizedTwrNullForShortPeriod(): void
	{
		// Perioda kratší než 180 dní → null (annualizace by dávala nesmyslné hodnoty)
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 105_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-03-01'),
		);

		self::assertNull($value->getAnnualizedTwr());
	}

	public function testGetAnnualizedTwrForExactlyOneYear(): void
	{
		// Přesně 365 dní → annualizovaný TWR = TWR
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-12-31'),
		);

		self::assertEqualsWithDelta(
			$value->getTimeWeightedReturn(),
			$value->getAnnualizedTwr() ?? 0.0,
			0.0001,
		);
	}

	public function testGetAnnualizedTwrForTwoYears(): void
	{
		// Za 2 roky (730 dní): TWR = 21% → annualizovaný ~ 10%
		// ((1 + 0.21)^(365/730)) - 1 ≈ 0.10 (10%)
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 121_000,
			startDate: new ImmutableDateTime('2022-01-01'),
			endDate: new ImmutableDateTime('2023-12-31'),
		);

		$annualized = $value->getAnnualizedTwr();
		self::assertNotNull($annualized);
		// TWR je ~21%, annualizovaný by měl být ~10%
		self::assertGreaterThan(9.0, $annualized);
		self::assertLessThan(11.0, $annualized);
	}

	public function testGetAnnualizedTwrForExactly180Days(): void
	{
		// Přesně 180 dní → null (hraniční případ, <= 180 vrací null)
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 105_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-06-29'),
		);

		self::assertNull($value->getAnnualizedTwr());
	}

	public function testGetAnnualizedTwrFor181Days(): void
	{
		// 181 dní → annualizovaný TWR by měl být nenullový
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 105_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-06-30'),
		);

		self::assertNotNull($value->getAnnualizedTwr());
	}

	// -------------------------------------------------------------------------
	// getMoneyWeightedReturn — bez datumů
	// -------------------------------------------------------------------------

	public function testGetMoneyWeightedReturnNullWithoutDates(): void
	{
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
		);

		self::assertNull($value->getMoneyWeightedReturn());
	}

	// -------------------------------------------------------------------------
	// getMoneyWeightedReturn — Simple Dietz fallback (< 2 záznamy)
	// -------------------------------------------------------------------------

	public function testGetMoneyWeightedReturnSimpleDietzFallbackShortPeriod(): void
	{
		// Simple Dietz (bez detailních dat), krátká perioda (30 dní) → neannualizuje
		// gain = 110k - 100k - 0 = 10k
		// denominator = 100k + 0/2 = 100k
		// MWR = 10k / 100k * 100 = 10%
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
		);

		$mwr = $value->getMoneyWeightedReturn();
		self::assertNotNull($mwr);
		self::assertEqualsWithDelta(10.0, $mwr, 0.0001);
	}

	public function testGetMoneyWeightedReturnSimpleDietzWithCashFlow(): void
	{
		// Simple Dietz s cash flow, krátká perioda
		// investedAtStart = 100k, investedAtEnd = 120k → cashFlow = 20k
		// gain = 125k - 100k - 20k = 5k
		// denominator = 100k + 20k/2 = 110k
		// MWR = 5k / 110k * 100 ≈ 4.545%
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 125_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
		);

		$mwr = $value->getMoneyWeightedReturn();
		self::assertNotNull($mwr);
		self::assertEqualsWithDelta(4.5455, $mwr, 0.001);
	}

	// -------------------------------------------------------------------------
	// getMoneyWeightedReturn — Modified Dietz (>= 2 záznamy)
	// -------------------------------------------------------------------------

	public function testGetMoneyWeightedReturnModifiedDietzNoNewInvestments(): void
	{
		// Modified Dietz: žádné nové vklady — výsledek se rovná Simple Dietz
		// gain = 110k - 100k - 0 = 10k
		// denominator = 100k (žádné delta CF)
		// MWR = 10%
		$cashFlowData = [
			['date' => new ImmutableDateTime('2024-01-01'), 'amount' => 100_000.0],
			['date' => new ImmutableDateTime('2024-01-31'), 'amount' => 100_000.0],
		];

		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
			cashFlowData: $cashFlowData,
		);

		$mwr = $value->getMoneyWeightedReturn();
		self::assertNotNull($mwr);
		self::assertEqualsWithDelta(10.0, $mwr, 0.0001);
	}

	public function testGetMoneyWeightedReturnModifiedDietzWithMidPeriodInvestment(): void
	{
		// Modified Dietz: vklad 20k uprostřed 30denní periody (den 15)
		// cashFlowData: 100k → 120k na den 15
		// gain = 125k - 100k - 20k = 5k
		// daysSinceStart = 15, totalDays = 30, weight = (30-15)/30 = 0.5
		// denominator = 100k + 20k * 0.5 = 110k
		// MWR = 5k / 110k * 100 ≈ 4.545%
		$cashFlowData = [
			['date' => new ImmutableDateTime('2024-01-01'), 'amount' => 100_000.0],
			['date' => new ImmutableDateTime('2024-01-16'), 'amount' => 120_000.0],
			['date' => new ImmutableDateTime('2024-01-31'), 'amount' => 120_000.0],
		];

		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 125_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
			cashFlowData: $cashFlowData,
		);

		$mwr = $value->getMoneyWeightedReturn();
		self::assertNotNull($mwr);
		self::assertEqualsWithDelta(4.5455, $mwr, 0.001);
	}

	public function testGetMoneyWeightedReturnModifiedDietzEarlyInvestmentHigherThanLate(): void
	{
		// Vklad na začátku → vyšší váha → vyšší denominator → nižší MWR
		// Vklad na konci → nižší váha → nižší denominator → vyšší MWR
		// Tento test ověřuje, že timing vkladu ovlivňuje výsledek

		// Vklad z 100k→120k na den 5 (weight = (30-5)/30 ≈ 0.833)
		// denominator = 100k + 20k * 0.833 = 116.67k
		// MWR_early = 5k / 116.67k * 100 ≈ 4.286%
		$cashFlowEarly = [
			['date' => new ImmutableDateTime('2024-01-01'), 'amount' => 100_000.0],
			['date' => new ImmutableDateTime('2024-01-06'), 'amount' => 120_000.0],
			['date' => new ImmutableDateTime('2024-01-31'), 'amount' => 120_000.0],
		];

		// Vklad z 100k→120k na den 25 (weight = (30-25)/30 ≈ 0.167)
		// denominator = 100k + 20k * 0.167 = 103.33k
		// MWR_late = 5k / 103.33k * 100 ≈ 4.839%
		$cashFlowLate = [
			['date' => new ImmutableDateTime('2024-01-01'), 'amount' => 100_000.0],
			['date' => new ImmutableDateTime('2024-01-26'), 'amount' => 120_000.0],
			['date' => new ImmutableDateTime('2024-01-31'), 'amount' => 120_000.0],
		];

		$valueEarly = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 125_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
			cashFlowData: $cashFlowEarly,
		);

		$valueLate = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 120_000,
			valueAtStart: 100_000,
			valueAtEnd: 125_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
			cashFlowData: $cashFlowLate,
		);

		$mwrEarly = $valueEarly->getMoneyWeightedReturn();
		$mwrLate = $valueLate->getMoneyWeightedReturn();

		self::assertNotNull($mwrEarly);
		self::assertNotNull($mwrLate);

		// Pozdější vklad → vyšší MWR (peníze musí za kratší dobu vydělat stejně)
		self::assertGreaterThan($mwrEarly, $mwrLate);
	}

	public function testGetMoneyWeightedReturnAnnualizedForLongPeriod(): void
	{
		// Pro periody > 180 dní se MWR annualizuje
		// Jednoroční perioda, zisk 10% → annualizovaný výsledek blízko 10%
		$cashFlowData = [
			['date' => new ImmutableDateTime('2024-01-01'), 'amount' => 100_000.0],
			['date' => new ImmutableDateTime('2024-12-31'), 'amount' => 100_000.0],
		];

		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 110_000,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-12-31'),
			cashFlowData: $cashFlowData,
		);

		$mwr = $value->getMoneyWeightedReturn();
		self::assertNotNull($mwr);
		// Pro roční periodu annualizovaný výnos ≈ původní výnos
		self::assertGreaterThan(9.0, $mwr);
		self::assertLessThan(11.0, $mwr);
	}

	// -------------------------------------------------------------------------
	// Hraniční případy
	// -------------------------------------------------------------------------

	public function testGetMoneyWeightedReturnNullWhenDenominatorZero(): void
	{
		// Nulový počáteční kapitál a žádný cash flow → denominator = 0 → null
		$value = $this->createValue(
			investedAtStart: 0,
			investedAtEnd: 0,
			valueAtStart: 0,
			valueAtEnd: 0,
			startDate: new ImmutableDateTime('2024-01-01'),
			endDate: new ImmutableDateTime('2024-01-31'),
		);

		self::assertNull($value->getMoneyWeightedReturn());
	}

	public function testGettersMonthandLabel(): void
	{
		$value = new PortfolioStatisticTotalValue(
			month: 3,
			label: 'Březen 2024',
			investedAtStart: 100_000,
			investedAtEnd: 110_000,
			valueAtStart: 100_000,
			valueAtEnd: 115_000,
		);

		self::assertSame(3, $value->getMonth());
		self::assertSame('Březen 2024', $value->getLabel());
		self::assertSame(110_000.0, $value->getInvestedAtEnd());
		self::assertSame(100_000.0, $value->getInvestedAtStart());
		self::assertSame(100_000.0, $value->getValueAtStart());
		self::assertSame(115_000.0, $value->getValueAtEnd());
	}

	public function testGetClosedPositionsProfitAndDividends(): void
	{
		$value = $this->createValue(
			investedAtStart: 100_000,
			investedAtEnd: 100_000,
			valueAtStart: 100_000,
			valueAtEnd: 100_000,
			closedPositionsProfit: 3_000,
			dividends: 1_000,
		);

		self::assertSame(3_000.0, $value->getClosedPositionsProfitInPeriod());
		self::assertSame(1_000.0, $value->getDividendsInPeriod());
		self::assertSame(3_000.0, $value->getTotalProfitWithClosedPositions());
		self::assertSame(1_000.0, $value->getTotalProfitWithDividends());
	}

}
