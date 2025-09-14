<?php

declare(strict_types = 1);

namespace App\UI\Filter;

use App\Currency\CurrencyEnum;

class CompactCurrencyFilter
{

	public static function format(
		float|int $value,
		CurrencyEnum|null $currencyEnum = null,
		int $precision = 1,
	): string
	{
		$absValue = abs($value);
		$unit = '';
		$divisor = 1;

		if ($absValue >= 1_000_000_000) {
			$unit = 'mld';
			$divisor = 1_000_000_000;
		} elseif ($absValue >= 1_000_000) {
			$unit = 'mil';
			$divisor = 1_000_000;
		} elseif ($absValue >= 1_000) {
			$unit = 'tis';
			$divisor = 1_000;
		}

		$compactValue = $value / $divisor;

		if ($unit === '') {
			$formattedValue = is_int($value) ? (string) $value : number_format($compactValue, $precision, '.', '');
		} else {
			if ($compactValue === (int) $compactValue) {
				$formattedValue = (string) $compactValue;
			} else {
				$formattedValue = number_format($compactValue, $precision, '.', '');
			}
		}

		$result = $formattedValue;

		if ($unit !== '') {
			$result .= ' ' . $unit;
		}

		if ($currencyEnum !== null) {
			$result .= ' ' . $currencyEnum->format();
		}

		return $result;
	}

}
