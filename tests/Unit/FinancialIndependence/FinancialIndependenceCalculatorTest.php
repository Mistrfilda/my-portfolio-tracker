<?php

declare(strict_types = 1);

namespace App\Test\Unit\FinancialIndependence;

use App\FinancialIndependence\FinancialIndependenceCalculationInput;
use App\FinancialIndependence\FinancialIndependenceCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use const INF;
use const NAN;

class FinancialIndependenceCalculatorTest extends TestCase
{

	public function testCalculatesTargetAndMonthlyProjection(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 100_000,
			targetMonthlyExpensesCzk: 1_000,
			monthlyContributionCzk: 1_000,
			annualNominalReturnPercentage: 0,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertSame(300_000.0, $result->targetPortfolioValueCzk);
		self::assertEqualsWithDelta(333.333333, $result->currentMonthlyWithdrawalCzk, 0.000001);
		self::assertSame(200_000.0, $result->portfolioGapCzk);
		self::assertEqualsWithDelta(33.333333, $result->fundedPercentage, 0.000001);
		self::assertSame(0.0, $result->netRealAnnualReturnPercentage);
		self::assertSame(0.0, $result->effectiveMonthlyReturnPercentage);
		self::assertSame(200, $result->monthsToTarget);
		self::assertCount(201, $result->projectionPoints);
		self::assertSame(0, $result->projectionPoints[0]->month);
		self::assertSame(100_000.0, $result->projectionPoints[0]->portfolioValueCzk);
		self::assertSame(1, $result->projectionPoints[1]->month);
		self::assertSame(101_000.0, $result->projectionPoints[1]->portfolioValueCzk);
		self::assertSame(300_000.0, $result->projectionPoints[200]->portfolioValueCzk);
	}

	public function testCalculatesExactNetRealAndEffectiveMonthlyReturn(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 1_000_000,
			targetMonthlyExpensesCzk: 1_000,
			monthlyContributionCzk: 0,
			annualNominalReturnPercentage: 10,
			annualInflationPercentage: 2,
			annualCostPercentage: 1,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));
		$expectedAnnualReturn = ((1.1 * 0.99 / 1.02) - 1) * 100;
		$expectedMonthlyReturn = (((1 + ($expectedAnnualReturn / 100)) ** (1 / 12)) - 1) * 100;

		self::assertEqualsWithDelta(
			$expectedAnnualReturn,
			$result->netRealAnnualReturnPercentage,
			0.000000000001,
		);
		self::assertEqualsWithDelta(
			$expectedMonthlyReturn,
			$result->effectiveMonthlyReturnPercentage,
			0.000000000001,
		);
	}

	public function testAppliesContributionAtEndOfMonth(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 1_200,
			targetMonthlyExpensesCzk: 100,
			monthlyContributionCzk: 100,
			annualNominalReturnPercentage: 12.682503013196978,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertEqualsWithDelta(1.0, $result->effectiveMonthlyReturnPercentage, 0.000000000001);
		self::assertEqualsWithDelta(1_312.0, $result->projectionPoints[1]->portfolioValueCzk, 0.000000001);
	}

	public function testAlreadyReachedTargetReturnsOnlyCurrentProjectionPoint(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 400_000,
			targetMonthlyExpensesCzk: 1_000,
			monthlyContributionCzk: 1_000,
			annualNominalReturnPercentage: 5,
			annualInflationPercentage: 2,
			annualCostPercentage: 0.5,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertSame(0, $result->monthsToTarget);
		self::assertSame(0.0, $result->portfolioGapCzk);
		self::assertSame(100.0, $result->fundedPercentage);
		self::assertCount(1, $result->projectionPoints);
	}

	public function testZeroExpenseTargetIsReachedImmediately(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 0,
			targetMonthlyExpensesCzk: 0,
			monthlyContributionCzk: 0,
			annualNominalReturnPercentage: 0,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertSame(0.0, $result->targetPortfolioValueCzk);
		self::assertSame(0.0, $result->currentMonthlyWithdrawalCzk);
		self::assertSame(0.0, $result->portfolioGapCzk);
		self::assertSame(100.0, $result->fundedPercentage);
		self::assertSame(0, $result->monthsToTarget);
		self::assertCount(1, $result->projectionPoints);
	}

	public function testNegativeRealReturnCanStillReachTargetWithContributions(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 1_000,
			targetMonthlyExpensesCzk: 4,
			monthlyContributionCzk: 100,
			annualNominalReturnPercentage: 0,
			annualInflationPercentage: 12,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertLessThan(0, $result->netRealAnnualReturnPercentage);
		self::assertSame(3, $result->monthsToTarget);
		self::assertGreaterThanOrEqual(1_200, $result->projectionPoints[3]->portfolioValueCzk);
	}

	public function testReturnsNullWhenTargetIsNotReachedWithinOneHundredYears(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 0,
			targetMonthlyExpensesCzk: 4.003333333333333,
			monthlyContributionCzk: 1,
			annualNominalReturnPercentage: 0,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertNull($result->monthsToTarget);
		self::assertCount(1_201, $result->projectionPoints);
		self::assertSame(1_200, $result->projectionPoints[1_200]->month);
		self::assertSame(1_200.0, $result->projectionPoints[1_200]->portfolioValueCzk);
	}

	public function testCanReachTargetInLastMonthOfProjectionHorizon(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 0,
			targetMonthlyExpensesCzk: 4,
			monthlyContributionCzk: 1,
			annualNominalReturnPercentage: 0,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertSame(1_200, $result->monthsToTarget);
		self::assertCount(1_201, $result->projectionPoints);
	}

	public function testReturnsNullWhenTargetIsNotReachedWithinPersonalProjectionHorizon(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(
			new FinancialIndependenceCalculationInput(
				currentPortfolioValueCzk: 100_000,
				targetMonthlyExpensesCzk: 1_000,
				monthlyContributionCzk: 1_000,
				annualNominalReturnPercentage: 0,
				annualInflationPercentage: 0,
				annualCostPercentage: 0,
				withdrawalRatePercentage: 4,
				birthYear: 1996,
				plannedLifespanAge: 80,
			),
			projectionHorizonMonths: 199,
		);

		self::assertNull($result->monthsToTarget);
		self::assertCount(200, $result->projectionPoints);
		self::assertSame(199, $result->projectionPoints[199]->month);
		self::assertSame(299_000.0, $result->projectionPoints[199]->portfolioValueCzk);
	}

	public function testCanReachTargetInLastMonthOfPersonalProjectionHorizon(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(
			new FinancialIndependenceCalculationInput(
				currentPortfolioValueCzk: 100_000,
				targetMonthlyExpensesCzk: 1_000,
				monthlyContributionCzk: 1_000,
				annualNominalReturnPercentage: 0,
				annualInflationPercentage: 0,
				annualCostPercentage: 0,
				withdrawalRatePercentage: 4,
				birthYear: 1996,
				plannedLifespanAge: 80,
			),
			projectionHorizonMonths: 200,
		);

		self::assertSame(200, $result->monthsToTarget);
		self::assertCount(201, $result->projectionPoints);
		self::assertSame(300_000.0, $result->projectionPoints[200]->portfolioValueCzk);
	}

	public function testRejectsNegativeProjectionHorizon(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Projection horizon must be greater than or equal to zero months.');

		(new FinancialIndependenceCalculator())->calculate(
			new FinancialIndependenceCalculationInput(
				currentPortfolioValueCzk: 100_000,
				targetMonthlyExpensesCzk: 1_000,
				monthlyContributionCzk: 1_000,
				annualNominalReturnPercentage: 0,
				annualInflationPercentage: 0,
				annualCostPercentage: 0,
				withdrawalRatePercentage: 4,
				birthYear: 1996,
				plannedLifespanAge: 80,
			),
			projectionHorizonMonths: -1,
		);
	}

	public function testCompleteAnnualLossDoesNotProduceInvalidMonthlyReturn(): void
	{
		$result = (new FinancialIndependenceCalculator())->calculate(new FinancialIndependenceCalculationInput(
			currentPortfolioValueCzk: 0,
			targetMonthlyExpensesCzk: 0.5,
			monthlyContributionCzk: 100,
			annualNominalReturnPercentage: -100,
			annualInflationPercentage: 0,
			annualCostPercentage: 0,
			withdrawalRatePercentage: 4,
			birthYear: 1996,
			plannedLifespanAge: 80,
		));

		self::assertSame(-100.0, $result->netRealAnnualReturnPercentage);
		self::assertSame(-100.0, $result->effectiveMonthlyReturnPercentage);
		self::assertNull($result->monthsToTarget);
		self::assertSame(100.0, $result->projectionPoints[1]->portfolioValueCzk);
		self::assertSame(100.0, $result->projectionPoints[2]->portfolioValueCzk);
	}

	#[DataProvider('invalidInputProvider')]
	public function testRejectsInvalidInput(
		FinancialIndependenceCalculationInput $input,
		string $expectedMessage,
	): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($expectedMessage);

		(new FinancialIndependenceCalculator())->calculate($input);
	}

	/**
	 * @return iterable<string, array{FinancialIndependenceCalculationInput, string}>
	 */
	public static function invalidInputProvider(): iterable
	{
		$validValues = [
			'currentPortfolioValueCzk' => 100_000.0,
			'targetMonthlyExpensesCzk' => 20_000.0,
			'monthlyContributionCzk' => 10_000.0,
			'annualNominalReturnPercentage' => 7.0,
			'annualInflationPercentage' => 2.0,
			'annualCostPercentage' => 0.5,
			'withdrawalRatePercentage' => 3.5,
			'birthYear' => 1996,
			'plannedLifespanAge' => 80,
		];

		yield 'negative portfolio value' => [
			self::createInput($validValues, 'currentPortfolioValueCzk', -1),
			'Current portfolio value must be a finite number greater than or equal to zero.',
		];

		yield 'non-finite portfolio value' => [
			self::createInput($validValues, 'currentPortfolioValueCzk', INF),
			'Current portfolio value must be a finite number greater than or equal to zero.',
		];

		yield 'negative expenses' => [
			self::createInput($validValues, 'targetMonthlyExpensesCzk', -1),
			'Target monthly expenses must be a finite number greater than or equal to zero.',
		];

		yield 'negative contribution' => [
			self::createInput($validValues, 'monthlyContributionCzk', -1),
			'Monthly contribution must be a finite number greater than or equal to zero.',
		];

		yield 'nominal return below total loss' => [
			self::createInput($validValues, 'annualNominalReturnPercentage', -100.01),
			'Annual nominal return must be a finite percentage greater than or equal to -100.',
		];

		yield 'non-finite nominal return' => [
			self::createInput($validValues, 'annualNominalReturnPercentage', NAN),
			'Annual nominal return must be a finite percentage greater than or equal to -100.',
		];

		yield 'inflation of negative one hundred percent' => [
			self::createInput($validValues, 'annualInflationPercentage', -100),
			'Annual inflation must be a finite percentage greater than -100.',
		];

		yield 'negative cost' => [
			self::createInput($validValues, 'annualCostPercentage', -0.01),
			'Annual cost must be a finite percentage between 0 and 100.',
		];

		yield 'cost above one hundred percent' => [
			self::createInput($validValues, 'annualCostPercentage', 100.01),
			'Annual cost must be a finite percentage between 0 and 100.',
		];

		yield 'zero withdrawal rate' => [
			self::createInput($validValues, 'withdrawalRatePercentage', 0),
			'Withdrawal rate must be a finite percentage greater than 0 and less than or equal to 100.',
		];

		yield 'withdrawal rate above one hundred percent' => [
			self::createInput($validValues, 'withdrawalRatePercentage', 100.01),
			'Withdrawal rate must be a finite percentage greater than 0 and less than or equal to 100.',
		];

		yield 'zero birth year' => [
			self::createInput($validValues, 'birthYear', 0),
			'Birth year must be greater than zero.',
		];

		yield 'zero planned lifespan age' => [
			self::createInput($validValues, 'plannedLifespanAge', 0),
			'Planned lifespan age must be between 1 and 150.',
		];

		yield 'planned lifespan age above one hundred and fifty' => [
			self::createInput($validValues, 'plannedLifespanAge', 151),
			'Planned lifespan age must be between 1 and 150.',
		];
	}

	/**
	 * @param array<string, float|int> $values
	 */
	private static function createInput(
		array $values,
		string $changedKey,
		float|int $changedValue,
	): FinancialIndependenceCalculationInput
	{
		$values[$changedKey] = $changedValue;

		return new FinancialIndependenceCalculationInput(...$values);
	}

}
