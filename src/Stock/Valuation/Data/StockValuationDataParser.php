<?php

declare(strict_types = 1);

namespace App\Stock\Valuation\Data;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Throwable;
use const XML_ELEMENT_NODE;

class StockValuationDataParser
{

	private DOMDocument $dom;

	private DOMXPath $xpath;

	public function __construct(string $html)
	{
		$this->dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$html = '<meta http-equiv="content-type" content="text/html; charset=utf-8">' . $html;
		$this->dom->loadHTML($html);
		libxml_clear_errors();
		$this->xpath = new DOMXPath($this->dom);
	}

	/**
	 * @return array<array<string, string|null>>
	 */
	public function parseStockData(): array
	{
		return [
			'basic_info' => $this->parseBasicInfo(),
			'valuation' => $this->parseValuationMetrics(),
			'financial_highlights' => $this->parseFinancialHighlights(),
			'balance_sheet' => $this->parseBalanceSheet(),
			'cash_flow' => $this->parseCashFlow(),
			'trading_info' => $this->parseTradingInfo(),
			'dividends' => $this->parseDividends(),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseBasicInfo(): array
	{
		return [
			'symbol' => $this->extractSymbol(),
			'company_name' => $this->extractCompanyName(),
			'current_price' => $this->extractTestIdValue('qsp-price'),
			'price_change' => $this->extractTestIdValue('qsp-price-change'),
			'price_change_percent' => $this->extractTestIdValue('qsp-price-change-percent'),
			'after_hours_price' => $this->extractTestIdValue('qsp-post-price'),
			'after_hours_change' => $this->extractTestIdValue('qsp-post-price-change'),
			'after_hours_change_percent' => $this->extractTestIdValue('qsp-post-price-change-percent'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseValuationMetrics(): array
	{
		return [
			'market_cap' => $this->findValueByText('Market Cap'),
			'enterprise_value' => $this->findValueByText('Enterprise Value'),
			'trailing_pe' => $this->findValueByText('Trailing P/E'),
			'forward_pe' => $this->findValueByText('Forward P/E'),
			'peg_ratio' => $this->findValueByText('PEG Ratio (5yr expected)'),
			'price_sales' => $this->findValueByText('Price/Sales'),
			'price_book' => $this->findValueByText('Price/Book'),
			'ev_revenue' => $this->findValueByText('Enterprise Value/Revenue'),
			'ev_ebitda' => $this->findValueByText('Enterprise Value/EBITDA'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseFinancialHighlights(): array
	{
		return [
			'revenue_ttm' => $this->findValueByText('Revenue'),
			'revenue_per_share' => $this->findValueByText('Revenue Per Share'),
			'quarterly_revenue_growth' => $this->findValueByText('Quarterly Revenue Growth'),
			'gross_profit' => $this->findValueByText('Gross Profit'),
			'ebitda' => $this->findValueByText('EBITDA'),
			'net_income' => $this->findValueByText('Net Income Avi to Common'),
			'diluted_eps' => $this->findValueByText('Diluted EPS'),
			'quarterly_earnings_growth' => $this->findValueByText('Quarterly Earnings Growth'),
			'profit_margin' => $this->findValueByText('Profit Margin'),
			'operating_margin' => $this->findValueByText('Operating Margin'),
			'return_on_assets' => $this->findValueByText('Return on Assets'),
			'return_on_equity' => $this->findValueByText('Return on Equity'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseBalanceSheet(): array
	{
		return [
			'total_cash' => $this->findValueByText('Total Cash'),
			'total_cash_per_share' => $this->findValueByText('Total Cash Per Share'),
			'total_debt' => $this->findValueByText('Total Debt'),
			'total_debt_equity' => $this->findValueByText('Total Debt/Equity'),
			'current_ratio' => $this->findValueByText('Current Ratio'),
			'book_value_per_share' => $this->findValueByText('Book Value Per Share'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseCashFlow(): array
	{
		return [
			'operating_cash_flow' => $this->findValueByText('Operating Cash Flow'),
			'levered_free_cash_flow' => $this->findValueByText('Levered Free Cash Flow'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseTradingInfo(): array
	{
		return [
			'beta' => $this->findValueByText('Beta (5Y Monthly)'),
			'52_week_change' => $this->findValueByText('52 Week Change'),
			'52_week_high' => $this->findValueByText('52 Week High'),
			'52_week_low' => $this->findValueByText('52 Week Low'),
			'50_day_ma' => $this->findValueByText('50-Day Moving Average'),
			'200_day_ma' => $this->findValueByText('200-Day Moving Average'),
			'avg_volume_3m' => $this->findValueByText('Avg Vol (3 month)'),
			'shares_outstanding' => $this->findValueByText('Shares Outstanding'),
			'float' => $this->findValueByText('Float'),
			'held_by_insiders' => $this->findValueByText('% Held by Insiders'),
			'held_by_institutions' => $this->findValueByText('% Held by Institutions'),
		];
	}

	/**
	 * @return array<string, string|null>
	 */
	private function parseDividends(): array
	{
		return [
			'forward_annual_dividend_rate' => $this->findValueByText('Forward Annual Dividend Rate'),
			'forward_annual_dividend_yield' => $this->findValueByText('Forward Annual Dividend Yield'),
			'trailing_annual_dividend_rate' => $this->findValueByText('Trailing Annual Dividend Rate'),
			'trailing_annual_dividend_yield' => $this->findValueByText('Trailing Annual Dividend Yield'),
			'payout_ratio' => $this->findValueByText('Payout Ratio'),
			'dividend_date' => $this->findValueByText('Dividend Date'),
			'ex_dividend_date' => $this->findValueByText('Ex-Dividend Date'),
		];
	}

	private function extractSymbol(): string|null
	{
		$nodes = $this->safeQuery('//h1');
		if ($nodes !== null && $nodes->length > 0) {
			$text = $nodes->item(0)?->textContent;
			if (preg_match('/\(([^)]+)\)/', $text ?? '', $matches) !== false) {
				return $matches[1];
			}
		}

		return null;
	}

	private function extractCompanyName(): string|null
	{
		$nodes = $this->safeQuery('//h1');
		if ($nodes !== null && $nodes->length > 0) {
			$text = $nodes->item(0)?->textContent;
			return preg_replace('/\s*\([^)]+\)$/', '', $text ?? '');
		}

		return null;
	}

	private function extractTestIdValue(string $testId): string|null
	{
		$nodes = $this->safeQuery('//*[@data-testid="' . $testId . '"]');
		if ($nodes !== null && $nodes->length > 0) {
			return trim($nodes->item(0)->textContent ?? '');
		}

		return null;
	}

	private function findValueByText(string $labelText): string|null
	{
		// Strategie 1: Přesná shoda textu
		$value = $this->findValueByTextStrategy($labelText, 'normalize-space(text())="' . trim($labelText) . '"');
		if ($value !== null) {
			return $value;
		}

		// Strategie 2: Obsahuje text
		$value = $this->findValueByTextStrategy(
			$labelText,
			'contains(normalize-space(text()), "' . trim($labelText) . '")',
		);
		if ($value !== null) {
			return $value;
		}

		// Strategie 3: Začíná textem
		$value = $this->findValueByTextStrategy(
			$labelText,
			'starts-with(normalize-space(text()), "' . trim($labelText) . '")',
		);
		if ($value !== null) {
			return $value;
		}

		// Strategie 4: Text obsahuje jen hlavní část (bez suffixů jako (ttm), (mrq) atd.)
		$coreText = preg_replace('/\s+\([^)]+\)/', '', $labelText);
		if ($coreText !== $labelText) {
			$value = $this->findValueByTextStrategy(
				$coreText ?? '',
				'contains(normalize-space(text()), "' . trim($coreText ?? '') . '")',
			);
			if ($value !== null) {
				return $value;
			}
		}

		return null;
	}

	private function findValueByTextStrategy(string $labelText, string $xpathCondition): string|null
	{
		// Hledáme TD element s daným textem
		$labelNodes = $this->safeQuery('//td[' . $xpathCondition . ']');

		if ($labelNodes !== null && $labelNodes->length > 0) {
			foreach ($labelNodes as $labelNode) {
				// Zkusíme najít následující TD element ve stejném řádku
				$valueNode = $this->getNextTdSibling($labelNode);
				if ($valueNode !== null) {
					$value = trim($valueNode->textContent);
					// Ověříme, že hodnota není prázdná nebo stejná jako label
					if (strlen($value) > 0 && $value !== trim($labelNode->textContent)) {
						return $value;
					}
				}

				// Zkusíme najít hodnotu v následujícím TD elementu pomocí XPath
				$parentTr = $labelNode->parentNode;
				if ($parentTr !== null && $parentTr->nodeName === 'tr') {
					$tds = $this->safeQuery('.//td', $parentTr);
					if ($tds !== null && $tds->length > 1) {
						// Najdeme pozici aktuálního TD a vezmeme následující
						for ($i = 0; $i < $tds->length - 1; $i++) {
							if ($tds->item($i) === $labelNode) {
								$nextTd = $tds->item($i + 1);
								if ($nextTd !== null) {
									$value = trim($nextTd->textContent);
									if (strlen($value) > 0) {
										return $value;
									}
								}

								break;
							}
						}
					}
				}
			}
		}

		return null;
	}

	private function getNextTdSibling(DOMNode $node): DOMNode|null
	{
		$sibling = $node->nextSibling;

		while ($sibling) {
			if ($sibling->nodeType === XML_ELEMENT_NODE && $sibling->nodeName === 'td') {
				return $sibling;
			}

			$sibling = $sibling->nextSibling;
		}

		return null;
	}

	/**
	 * @return DOMNodeList<DOMElement|DOMNode>|null
	 */
	private function safeQuery(string $expression, DOMNode|null $contextNode = null): DOMNodeList|null
	{
		try {
			if ($contextNode !== null) {
				$result = $this->xpath->query($expression, $contextNode);
			} else {
				$result = $this->xpath->query($expression);
			}

			// @phpstan-ignore-next-line
			return $result !== false ? $result : null;
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Konvertuje textové hodnoty na číselné pro další zpracování
	 */
	public function parseNumericValue(string|null $value): float|null
	{
		if ($value === null || $value === '--' || $value === 'N/A' || trim($value) === '') {
			return null;
		}

		$value = trim($value);
		if (str_contains($value, '%')) {
			$cleaned = preg_replace('/[^\d.,-]/', '', $value);
			if ($cleaned === null) {
				throw new StockValuationDataException();
			}

			return (float) str_replace(',', '.', $cleaned);
		}

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

		$cleaned = str_replace(',', '.', $cleaned);

		if (is_numeric($cleaned)) {
			return (float) $cleaned * $multiplier;
		}

		return null;
	}

}
