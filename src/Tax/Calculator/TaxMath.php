<?php

declare(strict_types = 1);

namespace App\Tax\Calculator;

final class TaxMath
{

	private const int FLOAT_PRECISION = 6;

	public static function ceilCzk(float $value): int
	{
		return (int) ceil(round($value, self::FLOAT_PRECISION));
	}

	public static function floorCzk(float $value): int
	{
		return (int) floor(round($value, self::FLOAT_PRECISION));
	}

	public static function roundCzk(float $value): int
	{
		return (int) round($value);
	}

	public static function floorToHundreds(int $amountCzk): int
	{
		if ($amountCzk <= 0) {
			return 0;
		}

		return intdiv($amountCzk, 100) * 100;
	}

	public static function ceilToHundreds(int $amountCzk): int
	{
		if ($amountCzk <= 0) {
			return 0;
		}

		return (int) ceil($amountCzk / 100) * 100;
	}

}
