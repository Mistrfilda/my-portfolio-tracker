// Selected numeric contracts from doc/openapi.yaml. Amounts are never formatted by main.
export const DASHBOARD_CHANNEL = 'dashboard:get';
export type Currency = 'CZK' | 'USD' | 'EUR' | 'GBP' | 'PLN' | 'NOK';

export interface PortfolioAmounts {
	investedAmount: number;
	currentValue: number;
	profit: number;
	profitPercent: number;
}

export interface DividendYear {
	year: number;
	grossAmount: number;
	netAmount: number;
}

export interface StockReference {
	id: string;
	name: string;
	ticker: string;
}

export interface StockMover {
	stock: StockReference;
	changePercent: number;
}

export interface DashboardSummary {
	generatedAt: string;
	currency: 'CZK';
	total: PortfolioAmounts;
	stocks: {
		amounts: PortfolioAmounts;
		closedPositionsProfitIncludingDividends: number;
		byTradingCurrency: { currency: Currency; currentValue: number }[];
	};
	crypto: PortfolioAmounts;
	portu: PortfolioAmounts;
	dividends: {
		byYear: DividendYear[];
		remainingForecast: DividendYear | null;
	};
	performance: {
		from: string | null;
		to: string | null;
		timeWeightedReturnPercent: number;
		annualizedTimeWeightedReturnPercent: number | null;
		moneyWeightedReturnPercent: number | null;
		xirrPercent: number | null;
	} | null;
	stockMovers: { best: StockMover[]; worst: StockMover[] };
}

export interface PortfolioDividend {
	id: string;
	stock: StockReference;
	exDate: string;
	paymentDate: string | null;
	currency: Currency;
	grossAmount: number;
	netAmount: number;
	reinvested: boolean;
}

export interface ExchangeRate {
	fromCurrency: Currency;
	toCurrency: Currency;
	rate: number;
	forDate: string;
	updatedAt: string;
	source: 'CNB' | 'ECB';
}

export type DashboardIssue =
	| 'network'
	| 'server'
	| 'permissions'
	| 'data-unavailable'
	| 'invalid-response'
	| 'session';

export type DashboardResult<T> =
	{ data: T; issue: null } | { data: null; issue: DashboardIssue };

export interface DashboardSnapshot {
	summary: DashboardResult<DashboardSummary>;
	dividends: DashboardResult<PortfolioDividend[]>;
	rates: DashboardResult<ExchangeRate[]>;
}

export interface DashboardBridge {
	get: () => Promise<DashboardSnapshot>;
}
