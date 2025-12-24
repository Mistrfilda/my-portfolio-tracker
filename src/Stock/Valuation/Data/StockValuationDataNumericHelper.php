<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

class StockValuationDataNumericHelper
{

	public static function parseNumericValue(string|null $value): float|null
	{
		if ($value === null || $value === '--' || $value === 'N/A' || trim($value) === '') {
			return null;
		}

		$value = trim($value);

		// Handle percentages
		if (str_contains($value, '%')) {
			$cleaned = preg_replace('/[^\d.,-]/', '', $value);
			if ($cleaned === null) {
				throw new StockValuationDataException();
			}

			return (float) str_replace(',', '.', $cleaned);
		}

		// Handle multipliers (B, M, K)
		$multiplier = 1;
		if (str_contains($value, 'B')) {
			$multiplier = 1000000000;
		} elseif (str_contains($value, 'M')) {
			$multiplier = 1000000;
		} elseif (str_contains($value, 'K')) {
			$multiplier = 1000;
		}

		$cleaned = preg_replace('/[^\d.,-]/', '', $value);
		if ($cleaned === null) {
			throw new StockValuationDataException();
		}

		// Detect number format (US: 1,234.56 vs EU: 1.234,56)
		$hasComma = str_contains($cleaned, ',');
		$hasDot = str_contains($cleaned, '.');

		if ($hasComma && $hasDot) {
			$lastCommaPos = strrpos($cleaned, ',');
			$lastDotPos = strrpos($cleaned, '.');

			if ($lastCommaPos !== false && $lastDotPos !== false && $lastDotPos > $lastCommaPos) {
				// US format: 1,234.56
				$cleaned = str_replace(',', '', $cleaned);
			} else {
				// EU format: 1.234,56
				$cleaned = str_replace('.', '', $cleaned);
				$cleaned = str_replace(',', '.', $cleaned);
			}
		} elseif ($hasComma) {
			$commaPos = strrpos($cleaned, ',');
			if ($commaPos !== false) {
				$charsAfterComma = strlen($cleaned) - $commaPos - 1;

				// Decimal separator if 1-2 chars after comma
				if ($charsAfterComma === 2 || $charsAfterComma === 1) {
					$cleaned = str_replace(',', '.', $cleaned);
				} else {
					$cleaned = str_replace(',', '', $cleaned);
				}
			}
		} elseif ($hasDot) {
			// Only dot present - need to detect if it's thousands separator or decimal point
			$dotPos = strrpos($cleaned, '.');
			if ($dotPos !== false) {
				$charsAfterDot = strlen($cleaned) - $dotPos - 1;

				// If exactly 3 chars after dot, it's likely a thousands separator (e.g., 1.234)
				// If 1-2 chars after dot, it's a decimal point (e.g., 123.45 or 123.5)
				if ($charsAfterDot === 3) {
					$cleaned = str_replace('.', '', $cleaned);
				}
				// Otherwise keep the dot as decimal separator
			}
		}

		if (is_numeric($cleaned)) {
			return (float) $cleaned * $multiplier;
		}

		return null;
	}

}
