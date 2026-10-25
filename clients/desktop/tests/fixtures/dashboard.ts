import type {
	DashboardSnapshot,
	DashboardSummary,
	ExchangeRate,
} from '../../src/shared/dashboard';

// Synthetic data only; never copied from a user's portfolio.
export const summary: DashboardSummary = {
	generatedAt: '2026-10-25T12:00:00+01:00',
	currency: 'CZK',
	total: {
		investedAmount: 1_000_000,
		currentValue: 1_212_500,
		profit: 212_500,
		profitPercent: 21.25,
	},
	stocks: {
		amounts: {
			investedAmount: 820_000,
			currentValue: 1_000_000,
			profit: 180_000,
			profitPercent: 21.95,
		},
		closedPositionsProfitIncludingDividends: 18_250,
		byTradingCurrency: [
			{ currency: 'USD', currentValue: 700_000 },
			{ currency: 'EUR', currentValue: 300_000 },
		],
	},
	crypto: {
		investedAmount: 130_000,
		currentValue: 150_000,
		profit: 20_000,
		profitPercent: 15.38,
	},
	portu: {
		investedAmount: 50_000,
		currentValue: 62_500,
		profit: 12_500,
		profitPercent: 25,
	},
	dividends: {
		byYear: [
			{ year: 2026, grossAmount: 29_500, netAmount: 25_075 },
			{ year: 2025, grossAmount: 21_000, netAmount: 17_850 },
		],
		remainingForecast: { year: 2026, grossAmount: 5_000, netAmount: 4_250 },
	},
	performance: {
		from: '2024-01-01',
		to: '2026-10-24',
		timeWeightedReturnPercent: 18.42,
		annualizedTimeWeightedReturnPercent: 6.34,
		moneyWeightedReturnPercent: 20.15,
		xirrPercent: null,
	},
	stockMovers: {
		best: [
			{
				stock: { id: 'stock-a', name: 'Example Technology', ticker: 'EXM' },
				changePercent: 2.48,
			},
			{
				stock: { id: 'stock-b', name: 'Example Energy', ticker: 'ENE' },
				changePercent: 0.76,
			},
		],
		worst: [
			{
				stock: { id: 'stock-c', name: 'Example Industries', ticker: 'IND' },
				changePercent: -1.32,
			},
		],
	},
};

export const dividendResponse = {
	items: [
		{
			id: 'record-a',
			dividend: {
				stock: { id: 'stock-a', name: 'Example Technology', ticker: 'EXM' },
				exDate: '2026-10-20',
				paymentDate: '2026-11-03',
			},
			currency: 'USD',
			grossAmount: 125,
			netAmount: 106.25,
			reinvested: false,
		},
	],
};

export const rates: ExchangeRate[] = [
	['USD', 'CZK', 23.1],
	['EUR', 'CZK', 24.6],
	['GBP', 'CZK', 29.2],
	['PLN', 'CZK', 5.8],
	['NOK', 'CZK', 2.1],
	['USD', 'EUR', 0.94],
	['EUR', 'USD', 1.06],
	['CZK', 'USD', 0.0433],
	['CZK', 'EUR', 0.0406],
	['CZK', 'GBP', 0.0342],
	['GBP', 'EUR', 1.18],
	['GBP', 'USD', 1.26],
].map(([fromCurrency, toCurrency, rate]) => ({
	fromCurrency: fromCurrency as ExchangeRate['fromCurrency'],
	toCurrency: toCurrency as ExchangeRate['toCurrency'],
	rate: rate as number,
	forDate: '2026-10-23',
	updatedAt: '2026-10-23T15:00:00Z',
	source: 'CNB',
}));

export function dashboardFixture(): DashboardSnapshot {
	return structuredClone({
		summary: { data: summary, issue: null },
		dividends: {
			data: [
				{
					id: 'record-a',
					stock: dividendResponse.items[0]!.dividend.stock,
					exDate: '2026-10-20',
					paymentDate: '2026-11-03',
					currency: 'USD',
					grossAmount: 125,
					netAmount: 106.25,
					reinvested: false,
				},
			],
			issue: null,
		},
		rates: { data: rates, issue: null },
	});
}
