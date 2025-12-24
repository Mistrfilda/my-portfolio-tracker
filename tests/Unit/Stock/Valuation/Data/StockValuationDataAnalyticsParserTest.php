<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Data;

use App\Stock\Valuation\Data\StockValuationDataAnalyticsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StockValuationDataAnalyticsParserTest extends TestCase
{

	private StockValuationDataAnalyticsParser $parser;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parser = new StockValuationDataAnalyticsParser();
	}

	#[DataProvider('provideValidAnalystPriceTargets')]
	public function testParseAnalystPriceTargets(string $input, array|null $expected): void
	{
		$result = $this->parser->parseAnalystPriceTargets($input);

		$this->assertSame($expected, $result);
	}

	/**
	 * @return array<string, array{string, array<string, string|null>|null}>
	 */
	public static function provideValidAnalystPriceTargets(): array
	{
		return [
			// Standard format
			'standard format' => [
				'150.00 Low 200.50 Average 195.75 Current 250.00 High',
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// With thousands separator
			'with thousands separator' => [
				'1,500.00 Low 2,000.50 Average 1,950.75 Current 2,500.00 High',
				[
					'low' => '1,500.00',
					'average' => '2,000.50',
					'current' => '1,950.75',
					'high' => '2,500.00',
				],
			],

			// EU format with comma as decimal
			'EU format' => [
				'150,00 Low 200,50 Average 195,75 Current 250,00 High',
				[
					'low' => '150,00',
					'average' => '200,50',
					'current' => '195,75',
					'high' => '250,00',
				],
			],

			// EU format with thousands separator
			'EU format with thousands' => [
				'1.500,00 Low 2.000,50 Average 1.950,75 Current 2.500,00 High',
				[
					'low' => '1.500,00',
					'average' => '2.000,50',
					'current' => '1.950,75',
					'high' => '2.500,00',
				],
			],

			// Without decimals
			'without decimals' => [
				'150 Low 200 Average 195 Current 250 High',
				[
					'low' => '150',
					'average' => '200',
					'current' => '195',
					'high' => '250',
				],
			],

			// Case insensitive
			'case insensitive' => [
				'150.00 low 200.50 average 195.75 current 250.00 high',
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// Mixed case
			'mixed case' => [
				'150.00 LOW 200.50 Average 195.75 CURRENT 250.00 High',
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// With extra whitespace
			'with extra whitespace' => [
				'150.00  Low  200.50  Average  195.75  Current  250.00  High',
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// With newlines
			'with newlines' => [
				"150.00 Low\n200.50 Average\n195.75 Current\n250.00 High",
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// With surrounding text
			'with surrounding text' => [
				'Analyst price targets: 150.00 Low, 200.50 Average, 195.75 Current, 250.00 High based on analysis',
				[
					'low' => '150.00',
					'average' => '200.50',
					'current' => '195.75',
					'high' => '250.00',
				],
			],

			// Very large numbers
			'very large numbers' => [
				'12345.67 Low 23456.78 Average 21111.22 Current 34567.89 High',
				[
					'low' => '12345.67',
					'average' => '23456.78',
					'current' => '21111.22',
					'high' => '34567.89',
				],
			],

			// One decimal place
			'one decimal place' => [
				'150.5 Low 200.5 Average 195.7 Current 250.5 High',
				[
					'low' => '150.5',
					'average' => '200.5',
					'current' => '195.7',
					'high' => '250.5',
				],
			],

			// Missing some values
			'missing some values' => [
				'150.00 Low 250.00 High',
				[
					'low' => '150.00',
					'average' => null,
					'current' => null,
					'high' => '250.00',
				],
			],

			// Only one value
			'only one value' => [
				'150.00 Low',
				[
					'low' => '150.00',
					'average' => null,
					'current' => null,
					'high' => null,
				],
			],

			// Empty string
			'empty string' => [
				'',
				null,
			],

			// No price targets
			'no price targets' => [
				'Some random text without any price targets',
				null,
			],
			// Only text without numbers - should return null
			'only text returns null' => [
				'Low Average Current High',
				null,
			],

			// Numbers without keywords - should return null
			'numbers without keywords returns null' => [
				'150.00 200.50 195.75 250.00',
				null,
			],
		];
	}

	public function testParseAnalystPriceTargetsReturnsNullForCompletelyEmptyResult(): void
	{
		$result = $this->parser->parseAnalystPriceTargets('No valid data here');

		$this->assertNull($result);
	}

	public function testParseAnalystPriceTargetsHandlesComplexRealWorldExample(): void
	{
		$input = <<<'TEXT'
		Analyst Insights
		Price Target
		124.17 Low
		181.61 Average
		176.25 Current
		280.00 High
		Based on 45 analysts offering 12 month price targets
		TEXT;

		$result = $this->parser->parseAnalystPriceTargets($input);

		$this->assertIsArray($result);
		$this->assertSame('124.17', $result['low']);
		$this->assertSame('181.61', $result['average']);
		$this->assertSame('176.25', $result['current']);
		$this->assertSame('280.00', $result['high']);
	}

	public function testParseAnalystPriceTargetsWithDifferentOrder(): void
	{
		$input = '250.00 High 195.75 Current 200.50 Average 150.00 Low';

		$result = $this->parser->parseAnalystPriceTargets($input);

		$this->assertIsArray($result);
		$this->assertSame('150.00', $result['low']);
		$this->assertSame('200.50', $result['average']);
		$this->assertSame('195.75', $result['current']);
		$this->assertSame('250.00', $result['high']);
	}

}
