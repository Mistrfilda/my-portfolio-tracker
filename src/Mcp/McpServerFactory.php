<?php

declare(strict_types = 1);

namespace App\Mcp;

use App\Mcp\Prompt\InvestmentPrompts;
use App\Mcp\Tool\AiAnalysesTool;
use App\Mcp\Tool\InvestmentPlansTool;
use App\Mcp\Tool\PortfolioOverviewTool;
use App\Mcp\Tool\SearchStocksTool;
use App\Mcp\Tool\SimulateStockPurchaseTool;
use App\Mcp\Tool\StockDividendsTool;
use App\Mcp\Tool\StockPriceHistoryTool;
use App\Mcp\Tool\StockValuationDetailTool;
use App\Mcp\Tool\WatchlistTool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Builder;
use Mcp\Server\Capability\Registry\ReferenceHandler;
use Mcp\Server\Server;
use Psr\Log\LoggerInterface;

class McpServerFactory
{

	private const string SERVER_NAME = 'my-portfolio-tracker';

	private const string SERVER_VERSION = '1.0.0';

	private const string SERVER_DESCRIPTION = 'Read-only MCP server for personal stock portfolio, valuations, '
		. 'dividends, AI analyses, investment plans, and purchase simulation.';

	private const string SERVER_INSTRUCTIONS = 'All tools are strictly read-only and scoped to the authenticated investor. '
		. 'Always inspect data freshness timestamps (generatedAt, priceDownloadedAt, importedAt, processedAt) '
		. 'and check investorInstructions from get_portfolio_overview before giving buy or rebalancing recommendations.';

	public function __construct(
		private LoggerInterface $logger,
		private PortfolioOverviewTool $portfolioOverviewTool,
		private WatchlistTool $watchlistTool,
		private SearchStocksTool $searchStocksTool,
		private StockPriceHistoryTool $stockPriceHistoryTool,
		private StockDividendsTool $stockDividendsTool,
		private StockValuationDetailTool $stockValuationDetailTool,
		private AiAnalysesTool $aiAnalysesTool,
		private InvestmentPlansTool $investmentPlansTool,
		private SimulateStockPurchaseTool $simulateStockPurchaseTool,
		private InvestmentPrompts $investmentPrompts,
	)
	{
	}

	public function create(): Server
	{
		return $this->createBuilder()
			->addTool(
				[$this->portfolioOverviewTool, 'execute'],
				name: 'get_portfolio_overview',
				title: 'Portfolio Overview',
				description: 'Returns the authenticated investor stock portfolio overview in CZK, '
					. 'open positions with weights, sector/currency allocations, and investorInstructions.',
				annotations: $this->createReadOnlyToolAnnotations('Portfolio Overview'),
			)
			->addTool(
				[$this->watchlistTool, 'execute'],
				name: 'get_watchlist',
				title: 'Stock Watchlist',
				description: 'Returns watched stock assets with current price, model fair value consensus, '
					. 'margin of safety, and optional simple watchlist items with recommended entry prices.',
				annotations: $this->createReadOnlyToolAnnotations('Stock Watchlist'),
			)
			->addTool(
				[$this->searchStocksTool, 'execute'],
				name: 'search_stocks',
				title: 'Search Stocks',
				description: 'Searches stock assets by ticker, company name, or sector/industry '
					. 'with portfolio, watchlist, and valuation availability flags.',
				annotations: $this->createReadOnlyToolAnnotations('Search Stocks'),
			)
			->addTool(
				[$this->stockPriceHistoryTool, 'execute'],
				name: 'get_stock_price_history',
				title: 'Stock Price History',
				description: 'Returns stock price history for a period (1M, 3M, 6M, 1Y, ALL) downsampled '
					. 'to maxPoints, period min/max/change, and 52-week high/low distance.',
				annotations: $this->createReadOnlyToolAnnotations('Stock Price History'),
			)
			->addTool(
				[$this->stockDividendsTool, 'execute'],
				name: 'get_stock_dividends',
				title: 'Stock Dividends',
				description: 'Returns dividend yield metrics, payout ratio, safety score, upcoming and recent '
					. 'dividends for a stock (scope="stock") or portfolio dividend summary in CZK (scope="portfolio").',
				annotations: $this->createReadOnlyToolAnnotations('Stock Dividends'),
			)
			->addTool(
				[$this->stockValuationDetailTool, 'execute'],
				name: 'get_stock_valuation_detail',
				title: 'Stock Valuation Detail',
				description: 'Returns 36 fundamental metrics with import timestamps, all 13 valuation models, '
					. 'fair value consensus, margin of safety, industry comparison, fundamental changes vs '
					. 'previous import, and current user position summary.',
				annotations: $this->createReadOnlyToolAnnotations('Stock Valuation Detail'),
			)
			->addTool(
				[$this->aiAnalysesTool, 'execute'],
				name: 'get_ai_analyses',
				title: 'AI Analyses',
				description: 'Returns recent AI analysis runs (portfolio, watchlist, market, daily_brief, single_stock) '
					. 'with action checklists and optional per-stock historical AI evaluation results, without raw prompts.',
				annotations: $this->createReadOnlyToolAnnotations('AI Analyses'),
			)
			->addTool(
				[$this->investmentPlansTool, 'execute'],
				name: 'get_investment_plans',
				title: 'Investment Plans',
				description: 'Returns saved AI investment plans with requested amount, portfolio context, '
					. 'allocations, portfolio impact, alternatives, key risks, and reference AI analysis run.',
				annotations: $this->createReadOnlyToolAnnotations('Investment Plans'),
			)
			->addTool(
				[$this->simulateStockPurchaseTool, 'execute'],
				name: 'simulate_stock_purchase',
				title: 'Simulate Stock Purchase',
				description: 'Runs an in-memory read-only what-if purchase simulation without writing to the database. '
					. 'Computes shares, new average price, before/after position and sector weights, and estimated annual dividend.',
				annotations: $this->createReadOnlyToolAnnotations('Simulate Stock Purchase'),
			)
			->addPrompt(
				[$this->investmentPrompts, 'evaluateBuyCandidate'],
				name: 'evaluate_buy_candidate',
				title: 'Evaluate Buy Candidate',
				description: 'Guides a structured evaluation of a single stock candidate using portfolio context, '
					. 'investorInstructions, valuation models, dividends, prior AI analyses, and purchase simulation.',
			)
			->addPrompt(
				[$this->investmentPrompts, 'compareBuyCandidates'],
				name: 'compare_buy_candidates',
				title: 'Compare Buy Candidates',
				description: 'Compares 2 to 5 comma-separated stock tickers side-by-side by valuation, '
					. 'margin of safety, dividends, sector overlap, and simulated purchase impact.',
			)
			->addPrompt(
				[$this->investmentPrompts, 'portfolioRebalanceCheck'],
				name: 'portfolio_rebalance_check',
				title: 'Portfolio Rebalance Check',
				description: 'Reviews portfolio concentration, sector/currency exposure, watchlist opportunities, '
					. 'recent AI checklists, and investment plans against investorInstructions.',
			)
			->build();
	}

	public function createBuilder(): Builder
	{
		return Server::builder()
			->setServerInfo(
				self::SERVER_NAME,
				self::SERVER_VERSION,
				self::SERVER_DESCRIPTION,
			)
			->setInstructions(self::SERVER_INSTRUCTIONS)
			->setLogger($this->logger)
			->setReferenceHandler(new McpReferenceHandler(new ReferenceHandler()))
			->setLazyLoading(false);
	}

	protected function createReadOnlyToolAnnotations(string $title): ToolAnnotations
	{
		return new ToolAnnotations(
			title: $title,
			readOnlyHint: true,
			destructiveHint: false,
			idempotentHint: true,
			openWorldHint: false,
		);
	}

}
