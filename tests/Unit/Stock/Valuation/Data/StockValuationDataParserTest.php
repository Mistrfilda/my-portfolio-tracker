<?php

declare(strict_types = 1);

namespace App\Test\Unit\Stock\Valuation\Data;

use App\Stock\Valuation\Data\StockValuationDataParser;
use App\Test\UpdatedTestCase;

class StockValuationDataParserTest extends UpdatedTestCase
{

	/**
	 * Tests if the parseStockData method correctly parses all sections of stock data.
	 */
	public function testParseStockDataReturnsCompleteExpectedData(): void
	{
		$html = <<<'HTML'
		<html>
			<body>
				<h1>Test Company (TCO)</h1>
				<div data-testid="qsp-price">150.55</div>
				<div data-testid="qsp-price-change">+2.34</div>
				<table>
					<tr>
						<td>Market Cap</td>
						<td>1.23B</td>
					</tr>
					<tr>
						<td>Enterprise Value</td>
						<td>980.45M</td>
					</tr>
					<tr>
						<td>Revenue</td>
						<td>340M</td>
					</tr>
					<tr>
						<td>Total Cash</td>
						<td>95M</td>
					</tr>
					<tr>
						<td>Operating Cash Flow</td>
						<td>120M</td>
					</tr>
					<tr>
						<td>Beta (5Y Monthly)</td>
						<td>1.08</td>
					</tr>
					<tr>
						<td>Forward Annual Dividend Rate</td>
						<td>2.15</td>
					</tr>
				</table>
			</body>
		</html>
HTML;

		$parser = new StockValuationDataParser($html);

		$expected = [
			'basic_info' => [
				'symbol' => 'TCO',
				'company_name' => 'Test Company',
				'current_price' => '150.55',
				'price_change' => '+2.34',
				'price_change_percent' => null,
				'after_hours_price' => null,
				'after_hours_change' => null,
				'after_hours_change_percent' => null,
			],
			'valuation' => [
				'market_cap' => '1.23B',
				'enterprise_value' => '980.45M',
				'trailing_pe' => null,
				'forward_pe' => null,
				'peg_ratio' => null,
				'price_sales' => null,
				'price_book' => null,
				'ev_revenue' => null,
				'ev_ebitda' => null,
			],
			'financial_highlights' => [
				'revenue_ttm' => '340M',
				'revenue_per_share' => null,
				'quarterly_revenue_growth' => null,
				'gross_profit' => null,
				'ebitda' => null,
				'net_income' => null,
				'diluted_eps' => null,
				'quarterly_earnings_growth' => null,
				'profit_margin' => null,
				'operating_margin' => null,
				'return_on_assets' => null,
				'return_on_equity' => null,
			],
			'balance_sheet' => [
				'total_cash' => '95M',
				'total_cash_per_share' => null,
				'total_debt' => null,
				'total_debt_equity' => null,
				'current_ratio' => null,
				'book_value_per_share' => null,
			],
			'cash_flow' => [
				'operating_cash_flow' => '120M',
				'levered_free_cash_flow' => null,
			],
			'trading_info' => [
				'beta' => '1.08',
				'52_week_change' => null,
				'52_week_high' => null,
				'52_week_low' => null,
				'50_day_ma' => null,
				'200_day_ma' => null,
				'avg_volume_3m' => null,
				'shares_outstanding' => null,
				'float' => null,
				'held_by_insiders' => null,
				'held_by_institutions' => null,
			],
			'dividends' => [
				'forward_annual_dividend_rate' => '2.15',
				'forward_annual_dividend_yield' => null,
				'trailing_annual_dividend_rate' => null,
				'trailing_annual_dividend_yield' => null,
				'payout_ratio' => null,
				'dividend_date' => null,
				'ex_dividend_date' => null,
			],
		];

		$result = $parser->parseStockData();

		$this->assertEquals($expected, $result);
	}

	/**
	 * Tests if the parseStockData method handles missing sections in the HTML.
	 */
	public function testParseStockDataWithMissingSections(): void
	{
		$html = <<<'HTML'
		<html>
			<body>
				<h1>Partial Company (PCO)</h1>
				<div data-testid="qsp-price">60.25</div>
			</body>
		</html>
HTML;

		$parser = new StockValuationDataParser($html);

		$expected = [
			'basic_info' => [
				'symbol' => 'PCO',
				'company_name' => 'Partial Company',
				'current_price' => '60.25',
				'price_change' => null,
				'price_change_percent' => null,
				'after_hours_price' => null,
				'after_hours_change' => null,
				'after_hours_change_percent' => null,
			],
			'valuation' => [
				'market_cap' => null,
				'enterprise_value' => null,
				'trailing_pe' => null,
				'forward_pe' => null,
				'peg_ratio' => null,
				'price_sales' => null,
				'price_book' => null,
				'ev_revenue' => null,
				'ev_ebitda' => null,
			],
			'financial_highlights' => [
				'revenue_ttm' => null,
				'revenue_per_share' => null,
				'quarterly_revenue_growth' => null,
				'gross_profit' => null,
				'ebitda' => null,
				'net_income' => null,
				'diluted_eps' => null,
				'quarterly_earnings_growth' => null,
				'profit_margin' => null,
				'operating_margin' => null,
				'return_on_assets' => null,
				'return_on_equity' => null,
			],
			'balance_sheet' => [
				'total_cash' => null,
				'total_cash_per_share' => null,
				'total_debt' => null,
				'total_debt_equity' => null,
				'current_ratio' => null,
				'book_value_per_share' => null,
			],
			'cash_flow' => [
				'operating_cash_flow' => null,
				'levered_free_cash_flow' => null,
			],
			'trading_info' => [
				'beta' => null,
				'52_week_change' => null,
				'52_week_high' => null,
				'52_week_low' => null,
				'50_day_ma' => null,
				'200_day_ma' => null,
				'avg_volume_3m' => null,
				'shares_outstanding' => null,
				'float' => null,
				'held_by_insiders' => null,
				'held_by_institutions' => null,
			],
			'dividends' => [
				'forward_annual_dividend_rate' => null,
				'forward_annual_dividend_yield' => null,
				'trailing_annual_dividend_rate' => null,
				'trailing_annual_dividend_yield' => null,
				'payout_ratio' => null,
				'dividend_date' => null,
				'ex_dividend_date' => null,
			],
		];

		$result = $parser->parseStockData();

		$this->assertEquals($expected, $result);
	}

}
