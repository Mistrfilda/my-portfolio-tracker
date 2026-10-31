import type {
	Currency,
	DashboardResult,
	PortfolioAmounts,
	StockReference,
} from './dashboard';

export const STOCKS_CHANNEL = 'stocks:get';

export interface StockPurchasePosition {
	id: string;
	orderDate: string;
	pieces: number;
	purchasePrice: number;
	amounts: PortfolioAmounts;
	brokerCost: { amount: number; currency: Currency };
}

export interface StockHolding {
	stock: StockReference & {
		exchange: string;
		currency: Currency;
		currentPrice: number;
		priceUpdatedAt: string | null;
	};
	pieces: number;
	averagePurchasePrice: number;
	amounts: PortfolioAmounts;
	brokerCurrency: Currency;
	brokerAmounts: PortfolioAmounts;
	currentValueCzk: number;
	portfolioSharePercent: number;
	dividends: {
		fromDate: string;
		grossPerShare: number;
		yieldOnPricePercent: number | null;
		yieldOnCostPercent: number | null;
	};
	positions: StockPurchasePosition[];
}

export interface StockPortfolio {
	generatedAt: string;
	currency: 'CZK';
	total: PortfolioAmounts;
	items: StockHolding[];
}

export type StockPortfolioResult = DashboardResult<StockPortfolio>;

export interface StocksBridge {
	get: () => Promise<StockPortfolioResult>;
}
