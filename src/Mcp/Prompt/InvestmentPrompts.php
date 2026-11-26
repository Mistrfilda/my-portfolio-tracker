<?php

declare(strict_types = 1);

namespace App\Mcp\Prompt;

use App\Mcp\Support\McpInvalidInputException;

class InvestmentPrompts
{

	/**
	 * @return list<array{role: string, content: string}>
	 */
	public function evaluateBuyCandidate(string $ticker, float|null $budgetCzk = null): array
	{
		$normalizedTicker = strtoupper(trim($ticker));
		if ($normalizedTicker === '') {
			throw new McpInvalidInputException('ticker must not be empty.');
		}

		if ($budgetCzk !== null && (!is_finite($budgetCzk) || $budgetCzk <= 0.0)) {
			throw new McpInvalidInputException('budgetCzk must be greater than zero when provided.');
		}

		$simulationInstruction = $budgetCzk !== null
			? sprintf(
				'5. Call `simulate_stock_purchase(ticker: "%s", amount: %.2f, amountCurrency: "CZK")` '
				. 'and report the new position weight, sector weight, average purchase price, and estimated annual net dividend.',
				$normalizedTicker,
				$budgetCzk,
			)
			: sprintf(
				'5. If a specific budget is discussed, call `simulate_stock_purchase(ticker: "%s", ...)` '
				. 'to quantify portfolio and sector impact.',
				$normalizedTicker,
			);

		$content = implode("\n", [
			sprintf(
				'Evaluate stock "%s" as a potential buy candidate using my portfolio tracker MCP tools:',
				$normalizedTicker,
			),
			'1. Call `get_portfolio_overview(includeInvestorInstructions: true)` '
			. 'to understand my current positions, sector/currency exposure, and `investorInstructions`.',
			sprintf(
				'2. Call `get_stock_valuation_detail(ticker: "%s")` to inspect fundamentals, all 13 valuation models, '
				. 'model consensus, margin of safety, industry comparison, and recent fundamental changes.',
				$normalizedTicker,
			),
			sprintf(
				'3. Call `get_stock_dividends(scope: "stock", ticker: "%s")` and '
				. '`get_stock_price_history(ticker: "%s", period: "1Y")` for dividend sustainability and 52-week price context.',
				$normalizedTicker,
				$normalizedTicker,
			),
			sprintf(
				'4. Call `get_ai_analyses(ticker: "%s", limit: 3)` to review prior AI evaluations and action checklists.',
				$normalizedTicker,
			),
			$simulationInstruction,
			'',
			'In your response:',
			'- Always cite data freshness timestamps (`priceDownloadedAt`, `importedAt`, `processedAt`) and flag stale data.',
			'- Evaluate explicitly against my `investorInstructions` (concentration, quality, valuation discipline, dividends).',
			'- Conclude with a clear bull case, bear case, key risks, and suggested entry price range.',
		]);

		return [
			[
				'role' => 'user',
				'content' => $content,
			],
		];
	}

	/**
	 * @return list<array{role: string, content: string}>
	 */
	public function compareBuyCandidates(string $tickers, float|null $budgetCzk = null): array
	{
		$parsedTickers = array_values(array_filter(
			array_map(static fn (string $part): string => strtoupper(trim($part)), explode(',', $tickers)),
			static fn (string $part): bool => $part !== '',
		));

		if (count($parsedTickers) < 2 || count($parsedTickers) > 5) {
			throw new McpInvalidInputException(
				'Provide between 2 and 5 comma-separated tickers (e.g. "MSFT, JNJ, V").',
			);
		}

		if ($budgetCzk !== null && (!is_finite($budgetCzk) || $budgetCzk <= 0.0)) {
			throw new McpInvalidInputException('budgetCzk must be greater than zero when provided.');
		}

		$tickerList = implode(', ', $parsedTickers);
		$budgetStep = $budgetCzk !== null
			? sprintf(
				'4. For each candidate, call `simulate_stock_purchase(ticker, amount: %.2f, amountCurrency: "CZK")` '
				. 'to compare exact portfolio weight, sector weight, and annual dividend impact.',
				$budgetCzk,
			)
			: '4. Optionally run `simulate_stock_purchase` for equal-sized hypothetical purchases to compare sector and weight impact.';

		$content = implode("\n", [
			sprintf('Compare these buy candidates side-by-side: %s.', $tickerList),
			'1. Call `get_portfolio_overview(includeInvestorInstructions: true)` to check my current allocation and `investorInstructions`.',
			'2. For each ticker, call `get_stock_valuation_detail(ticker, includeModelScenarios: false)` '
			. 'and `get_stock_dividends(scope: "stock", ticker)`.',
			'3. Call `get_ai_analyses(ticker, limit: 2)` for each candidate to see the latest AI assessment and target prices.',
			$budgetStep,
			'',
			'Present a concise comparison table covering: current price & freshness, consensus fair value & upside %, '
			. 'margin of safety status/confidence, industry P/E comparison, dividend yield & safety score, '
			. 'sector overlap with my portfolio, and simulated impact. '
			. 'Rank the candidates according to my `investorInstructions` and explain the tradeoffs.',
		]);

		return [
			[
				'role' => 'user',
				'content' => $content,
			],
		];
	}

	/**
	 * @return list<array{role: string, content: string}>
	 */
	public function portfolioRebalanceCheck(): array
	{
		$content = implode("\n", [
			'Perform a comprehensive read-only portfolio balance and concentration check:',
			'1. Call `get_portfolio_overview(includeInvestorInstructions: true)` '
			. 'to inspect all open positions, weights, sector/currency allocations, and my `investorInstructions`.',
			'2. Call `get_watchlist(includeSimpleWatchlist: true)` to see candidate replacements or underweight opportunities.',
			'3. Call `get_ai_analyses(type: "portfolio", limit: 2)` and `get_ai_analyses(type: "daily_brief", limit: 2)` '
			. 'to inspect recent conclusions and action checklists.',
			'4. Call `get_investment_plans(limit: 2)` to compare current weights with recent investment plans.',
			'5. Call `get_stock_dividends(scope: "portfolio")` for dividend income context.',
			'',
			'Highlight: single-stock or sector over-concentration relative to my `investorInstructions`, '
			. 'currency exposure risks, open items from recent AI checklists, data freshness warnings, '
			. 'and concrete candidates worth deeper valuation inspection.',
		]);

		return [
			[
				'role' => 'user',
				'content' => $content,
			],
		];
	}

}
