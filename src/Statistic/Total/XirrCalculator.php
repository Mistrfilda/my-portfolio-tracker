<?php

declare(strict_types = 1);

namespace App\Statistic\Total;

use Mistrfilda\Datetime\Types\ImmutableDateTime;

class XirrCalculator
{

	private const float TOLERANCE = 1e-7;

	private const int MAX_ITERATIONS = 100;

	/**
	 * Calculates XIRR (Internal Rate of Return) using Newton's method.
	 *
	 * @param array<array{date: ImmutableDateTime, amount: float}> $cashFlows
	 *        Array of cash flows where amount is negative for investments and positive for withdrawals/value.
	 * @return float|null Annual return as a decimal (e.g. 0.25 = 25%), or null if calculation fails.
	 */
	public static function calculate(array $cashFlows): float|null
	{
		if (count($cashFlows) < 2) {
			return null;
		}

		$firstDate = $cashFlows[0]['date'];
		$hasPositive = false;
		$hasNegative = false;

		/** @var array<array{days: float, amount: float}> $normalized */
		$normalized = [];
		foreach ($cashFlows as $cf) {
			$diff = $firstDate->diff($cf['date']);
			$days = $diff->days !== false ? (float) $diff->days : 0.0;
			$normalized[] = [
				'days' => $days,
				'amount' => $cf['amount'],
			];

			if ($cf['amount'] > 0) {
				$hasPositive = true;
			}

			if ($cf['amount'] < 0) {
				$hasNegative = true;
			}
		}

		// XIRR requires at least one positive and one negative cash flow
		if (!$hasPositive || !$hasNegative) {
			return null;
		}

		// Initial guess
		$rate = 0.1;

		for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
			$fValue = self::xirrFunction($normalized, $rate);
			$fDerivative = self::xirrDerivative($normalized, $rate);

			if (abs($fDerivative) < 1e-12) {
				// Derivative too small, try a different initial guess
				$rate += 0.1;
				continue;
			}

			$newRate = $rate - $fValue / $fDerivative;

			if (abs($newRate - $rate) < self::TOLERANCE) {
				return $newRate;
			}

			// Protection against divergence - rate must not go below -1
			if ($newRate <= -1.0) {
				$newRate = ($rate - 1.0) / 2.0;
			}

			$rate = $newRate;
		}

		// Fallback: try bisection if Newton's method fails
		return self::bisectionFallback($normalized);
	}

	/**
	 * Adjusts annualized XIRR to the return for a given period.
	 * Formula: (1 + xirr) ^ (days / 365) - 1
	 */
	public static function adjustForPeriod(float $annualizedXirr, int $days): float
	{
		if ($days <= 0) {
			return 0.0;
		}

		return ((1 + $annualizedXirr) ** ($days / 365)) - 1;
	}

	/**
	 * f(r) = Σ CF_i / (1 + r) ^ (d_i / 365)
	 *
	 * @param array<array{days: float, amount: float}> $normalized
	 */
	private static function xirrFunction(array $normalized, float $rate): float
	{
		$result = 0.0;
		foreach ($normalized as $cf) {
			$exponent = $cf['days'] / 365.0;
			$result += $cf['amount'] / ((1 + $rate) ** $exponent);
		}

		return $result;
	}

	/**
	 * f'(r) = Σ -CF_i × (d_i / 365) / (1 + r) ^ (d_i / 365 + 1)
	 *
	 * @param array<array{days: float, amount: float}> $normalized
	 */
	private static function xirrDerivative(array $normalized, float $rate): float
	{
		$result = 0.0;
		foreach ($normalized as $cf) {
			$exponent = $cf['days'] / 365.0;
			$result -= $cf['amount'] * $exponent / ((1 + $rate) ** ($exponent + 1));
		}

		return $result;
	}

	/**
	 * Bisection as a fallback method if Newton's method fails.
	 *
	 * @param array<array{days: float, amount: float}> $normalized
	 */
	private static function bisectionFallback(array $normalized): float|null
	{
		$low = -0.99;
		$high = 10.0;

		$fLow = self::xirrFunction($normalized, $low);

		// If signs are not opposite, try expanding the range
		if ($fLow * self::xirrFunction($normalized, $high) > 0) {
			$high = 100.0;
			if ($fLow * self::xirrFunction($normalized, $high) > 0) {
				return null;
			}
		}

		for ($i = 0; $i < 200; $i++) {
			$mid = ($low + $high) / 2.0;
			$fMid = self::xirrFunction($normalized, $mid);

			if (abs($fMid) < self::TOLERANCE) {
				return $mid;
			}

			if ($fLow * $fMid < 0) {
				$high = $mid;
			} else {
				$low = $mid;
				$fLow = $fMid;
			}
		}

		return ($low + $high) / 2.0;
	}

}
