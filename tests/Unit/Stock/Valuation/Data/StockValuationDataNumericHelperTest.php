<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Data;

use App\Stock\Valuation\Data\StockValuationDataNumericHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockValuationDataNumericHelperTest extends TestCase
{

	#[DataProvider('provideValidNumericValues')]
	public function testParseNumericValue(string|null $input, float|null $expected): void
	{
		$result = StockValuationDataNumericHelper::parseNumericValue($input);

		if ($expected === null) {
			$this->assertNull($result);
		} else {
			$this->assertEqualsWithDelta($expected, $result, 0.01);
		}
	}

	/**
	 * @return array<string, array{string|null, float|null}>
	 */
	public static function provideValidNumericValues(): array
	{
		return [
			// Null and empty values
			'null value' => [null, null],
			'empty string' => ['', null],
			'whitespace only' => ['   ', null],
			'dash placeholder' => ['--', null],
			'N/A placeholder' => ['N/A', null],

			// Simple numbers
			'integer' => ['123', 123.0],
			'float with dot' => ['123.45', 123.45],
			'float with comma' => ['123,45', 123.45],

			// US format (comma as thousands separator, dot as decimal)
			'US format thousands' => ['1,234', 1234.0],
			'US format with decimals' => ['1,234.56', 1234.56],
			'US format large number' => ['4,024.50', 4024.50],
			'US format millions' => ['1,234,567.89', 1234567.89],

			// EU format (dot as thousands separator, comma as decimal)
			'EU format thousands' => ['1.234', 1234.0],
			'EU format with decimals' => ['1.234,56', 1234.56],
			'EU format large number' => ['4.024,50', 4024.50],
			'EU format millions' => ['1.234.567,89', 1234567.89],

			// Percentages
			'percentage with dot' => ['12.5%', 12.5],
			'percentage with comma' => ['12,5%', 12.5],
			'percentage without decimals' => ['15%', 15.0],
			'negative percentage' => ['-5.25%', -5.25],

			// Multipliers (B, M, K)
			'billions' => ['1.5B', 1500000000.0],
			'millions' => ['50.5M', 50500000.0],
			'thousands' => ['123K', 123000.0],
			'billions US format' => ['1,234.5B', 1234500000000.0],
			'millions with comma' => ['50,5M', 50500000.0],

			// Edge cases with single separator
			'comma as decimal (short)' => ['12,5', 12.5],
			'comma as decimal (two digits)' => ['12,50', 12.50],
			'comma as thousands' => ['1,234', 1234.0],

			// Negative numbers
			'negative simple' => ['-123.45', -123.45],
			'negative with thousands' => ['-1,234.56', -1234.56],
			'negative with multiplier' => ['-5.5M', -5500000.0],
		];
	}

}
